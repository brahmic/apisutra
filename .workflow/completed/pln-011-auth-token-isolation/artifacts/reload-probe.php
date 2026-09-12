<?php

declare(strict_types=1);

// Аргумент — каталог снимка src из исходного commit 17d9f4c; текущий код не заменяется.
use Brahmic\ApiSutra\Auth\TokenAuthenticator;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Pipeline\Auth\AuthHandler;
use Brahmic\ApiSutra\Tests\Stubs\Requests\AuthRequest;
use Brahmic\ApiSutra\Tests\Support\ArrayCache;
use Brahmic\ApiSutra\Tests\Support\FakeSleeper;
use Brahmic\ApiSutra\Tests\Support\RecordingPipelineExecutor;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
$snapshot = $argv[1] ?? '';
if (!is_file($snapshot . '/src/Pipeline/Auth/AuthHandler.php')) {
    throw new RuntimeException('Укажите снимок исходного src');
}
spl_autoload_register(static function (string $class) use ($snapshot): void {
    $prefix = 'Brahmic\\ApiSutra\\';
    if (str_starts_with($class, $prefix)) {
        $file = $snapshot . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
}, prepend: true);

$store = new class extends ArrayCache {
    public string $tokenKey;

    public function add(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->set($this->tokenKey, ['token' => 'fixture-new-token', 'expires_at' => time() + 600], 600);
        return false;
    }
};
$auth = new TokenAuthenticator('fixture', 'password');
$store->tokenKey = $auth->getCacheKey();
$store->set($store->tokenKey, ['token' => 'fixture-old-token', 'expires_at' => time() - 1], 600);
$config = new ClientConfig(baseUrl: 'https://fixture.test', auth: $auth, cache: $store, timeout: 5);
$request = new AuthRequest('fixture');
$context = new PipelineContext($request, $config, 'fixture', preparedRequest: new PreparedRequest(HttpMethod::GET, 'https://fixture.test'));
$sleeper = new FakeSleeper();
$executor = new RecordingPipelineExecutor();
(new AuthHandler($config, $executor, $sleeper))->handleAuthentication($request, $context);
echo json_encode([
    'store_updated' => $store->get($store->tokenKey)['token'] === 'fixture-new-token',
    'sent_updated_token' => ($context->preparedRequest->headers['Authorization'] ?? null) === 'Bearer fixture-new-token',
    'wait_ms' => $sleeper->totalMs,
    'refresh_calls' => $executor->calls,
], JSON_THROW_ON_ERROR), "\n";
