<?php

declare(strict_types=1);

// Запуск из корня: php .workflow/completed/pln-011-auth-token-isolation/artifacts/probe.php
use Brahmic\ApiSutra\Auth\TokenAuthenticator;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Pipeline\Auth\AuthHandler;
use Brahmic\ApiSutra\Pipeline\Auth\AuthRefreshLock;
use Brahmic\ApiSutra\Tests\Stubs\Auth\LockAwareAuthenticator;
use Brahmic\ApiSutra\Tests\Stubs\Requests\AuthRequest;
use Brahmic\ApiSutra\Tests\Support\ArrayCache;
use Brahmic\ApiSutra\Tests\Support\FakeSleeper;
use Brahmic\ApiSutra\Tests\Support\LockingCache;
use Brahmic\ApiSutra\Tests\Support\RecordingPipelineExecutor;
use Brahmic\ApiSutra\Tests\Support\StrictCache;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

/** @param array<string, bool|int> $data */
$report = static function (string $case, array $data): void {
    echo json_encode(['case' => $case, ...$data], JSON_THROW_ON_ERROR), "\n";
};

foreach (['none' => null, 'psr16' => new ArrayCache(), 'add' => new LockingCache()] as $name => $cache) {
    $lock = new AuthRefreshLock($cache);
    $token = $lock->acquire('fixture.lock', 30);
    $lock->release('fixture.lock', $token);
    $report('release.' . $name, ['reacquired' => $lock->acquire('fixture.lock', 30) !== null]);
}

$cache = new ArrayCache();
$authA = new TokenAuthenticator('fixture-user', 'fixture-password-a');
$authB = new TokenAuthenticator('fixture-user', 'fixture-password-b');
$cache->set($authA->getCacheKey(), ['token' => 'fixture-token-a', 'expires_at' => time() + 600], 600);
$authB->setCache($cache);
$prepared = new PreparedRequest(HttpMethod::GET, 'https://b.fixture.test/resource');
$report('account.collision', [
    'same_storage_key' => $authA->getCacheKey() === $authB->getCacheKey(),
    'same_http_identity' => $authA->getCacheIdentity() === $authB->getCacheIdentity(),
    'sent_a_token' => ($authB->authenticate($prepared)->headers['Authorization'] ?? null) === 'Bearer fixture-token-a',
]);

$authServerB = new TokenAuthenticator('fixture-user', 'fixture-password-a');
$config = new ClientConfig(baseUrl: 'https://b.fixture.test', auth: $authServerB, cache: $cache, authRetryAttempts: 0);
$request = new AuthRequest('fixture');
$context = new PipelineContext($request, $config, 'fixture-trace', preparedRequest: $prepared);
(new AuthHandler($config, new RecordingPipelineExecutor()))->handleAuthentication($request, $context);
$report('server.collision', ['sent_a_token' => ($context->preparedRequest->headers['Authorization'] ?? null) === 'Bearer fixture-token-a']);

$authA->setCache($cache);
$authA->setCache(new ArrayCache());
$report('memory.rebind', ['kept_a_token' => ($authA->authenticate($prepared)->headers['Authorization'] ?? null) === 'Bearer fixture-token-a']);

try {
    (new TokenAuthenticator('fixture:user@example.test', 'fixture-password'))->setCache(new StrictCache());
    $report('key.strict', ['rejected' => false]);
} catch (InvalidArgumentException) {
    $report('key.strict', ['rejected' => true]);
}

// Детерминированная имитация смены владельца между get и delete; потоков/сна нет.
$racingCache = new class extends ArrayCache {
    public bool $replaceOwnerOnRead = false;

    public function add(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return !$this->has($key) && $this->set($key, $value, $ttl);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = parent::get($key, $default);
        if ($this->replaceOwnerOnRead) {
            $this->replaceOwnerOnRead = false;
            $this->set($key, 'fixture-new-owner', 30);
        }
        return $value;
    }
};
$lock = new AuthRefreshLock($racingCache);
$token = $lock->acquire('fixture.race', 30);
$racingCache->replaceOwnerOnRead = true;
$lock->release('fixture.race', $token);
$report('release.owner_race', ['new_owner_deleted' => !$racingCache->has('fixture.race')]);

$cache = new LockingCache();
$auth = new LockAwareAuthenticator('fixture-account');
$cache->set('auth_refresh_lock:' . $auth->getCacheKey(), 'fixture-other-owner', 60);
$config = new ClientConfig(baseUrl: 'https://fixture.test', auth: $auth, cache: $cache, timeout: 5);
$executor = new RecordingPipelineExecutor();
$sleeper = new FakeSleeper();
$context = new PipelineContext($request, $config, 'fixture-wait', preparedRequest: $prepared);
(new AuthHandler($config, $executor, $sleeper))->handleAuthentication($request, $context);
$report('lock.exhaustion', ['refresh_calls' => $executor->calls, 'authenticate_calls' => $auth->authenticateCalls, 'wait_ms' => $sleeper->totalMs]);
