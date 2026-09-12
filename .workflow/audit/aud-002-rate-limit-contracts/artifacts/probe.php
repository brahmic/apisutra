<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\RateLimitProbe;

use Brahmic\ApiSutra\Auth\BearerAuthenticator;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use Brahmic\ApiSutra\Exceptions\Request\RateLimitException;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\ArrayCache;
use Brahmic\ApiSutra\Tests\Support\StrictCache;
use Brahmic\ApiSutra\Tests\Support\VirtualClock;
use Brahmic\ApiSutra\Timing\ExecutionBudget;
use Brahmic\ApiSutra\Transport\MockTransport;
use Throwable;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

/** @param array<string, mixed> $values */
function emit(string $case, array $values): void
{
    echo json_encode(['case' => $case] + $values, JSON_THROW_ON_ERROR) . PHP_EOL;
}

/** @param list<ClientConfig> $configs */
function clients(string $case, array $configs): void
{
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $results = [];
    foreach ($configs as $config) {
        $result = (new TestClient($config, $transport))->send(new CacheProbeRequest())->raw();
        $results[] = [
            'success' => $result->isSuccess(), 'code' => $result->errors->first()?->code->value,
            'response_status' => $result->response?->status,
            'response_url' => $result->response?->request->url,
            'retry_after_exception' => $result->exception instanceof RateLimitException ? $result->exception->retryAfter : null,
            'retry_after_context' => $result->errors->first()?->context['retryAfter'] ?? null,
        ];
    }
    emit($case, ['results' => $results, 'http_calls' => count($transport->getRecorded())]);
}

clients('strict_store_key', [new ClientConfig(baseUrl: 'https://fixture.test', rateLimit: new RateLimitConfig(
    limit: 1, behavior: RateLimitBehavior::Throw, store: new StrictCache(),
))]);
$rate = new RateLimitConfig(limit: 1, behavior: RateLimitBehavior::Throw);
$config = new ClientConfig(baseUrl: 'https://fixture.test', rateLimit: $rate);
clients('new_client_resets_local_limit', [$config, $config]);
$store = new ArrayCache();
$rate = new RateLimitConfig(limit: 1, behavior: RateLimitBehavior::Throw, store: $store);
clients('same_url_distinct_credentials', [
    new ClientConfig(baseUrl: 'https://fixture.test', auth: new BearerAuthenticator('fixture-A'), rateLimit: $rate),
    new ClientConfig(baseUrl: 'https://fixture.test', auth: new BearerAuthenticator('fixture-B'), rateLimit: $rate),
]);
$rate = new RateLimitConfig(limit: 1, behavior: RateLimitBehavior::Throw, store: new ArrayCache(), key: 'orders');
clients('custom_key_across_urls', [
    new ClientConfig(baseUrl: 'https://first.fixture.test', rateLimit: $rate),
    new ClientConfig(baseUrl: 'https://second.fixture.test', rateLimit: $rate),
]);

$clock = new VirtualClock();
$store = new StrictCache();
$store->failWrites = true;
$config = new RateLimitConfig(limit: 1, behavior: RateLimitBehavior::Throw, store: $store);
$limiter = new RateLimiter($clock, $clock);
$limiter->acquire($config, 'quota');
$limiter->acquire($config, 'quota');
emit('failed_store_writes', ['allowed' => 2, 'stored' => $store->get('quota')]);

$clock = new VirtualClock();
$store = new ArrayCache();
$store->set('quota', ['count' => 1, 'reset' => $clock->unixTime() + 1], 60);
$sleeper = new class($clock, $store) implements SleeperInterface {
    public function __construct(private VirtualClock $clock, private ArrayCache $store) {}
    public function sleepMs(int $milliseconds): void
    {
        $this->clock->sleepMs($milliseconds);
        // Другой процесс занял единственное место в новом окне до пробуждения текущего.
        $this->store->set('quota', ['count' => 1, 'reset' => $this->clock->unixTime() + 60], 60);
    }
};
(new RateLimiter($clock, $sleeper))->acquire(new RateLimitConfig(limit: 1, period: 60, store: $store), 'quota');
emit('wake_overwrites_consumed_window', ['allowed_in_new_window' => 2, 'stored_count' => $store->get('quota')['count'], 'waits_ms' => $clock->waits]);

foreach ([['zero_limit', 0, 60], ['zero_period', 1, 0], ['negative_period', 1, -1]] as [$case, $limit, $period]) {
    $clock = new VirtualClock();
    $limiter = new RateLimiter($clock, $clock);
    $config = new RateLimitConfig(limit: $limit, period: $period);
    try {
        $limiter->acquire($config, 'quota');
        $limiter->acquire($config, 'quota');
        emit($case, ['accepted' => true, 'allowed' => 2, 'waits_ms' => $clock->waits]);
    } catch (Throwable $exception) {
        emit($case, ['accepted' => false, 'exception' => $exception::class]);
    }
}

$clock = new VirtualClock();
$limiter = new RateLimiter($clock, $clock);
$config = new RateLimitConfig(limit: 1, period: 60);
$limiter->acquire($config, 'quota');
try {
    $limiter->acquire($config, 'quota', new ExecutionBudget($clock, 100));
    emit('deadline_control', ['blocked' => false]);
} catch (Throwable $exception) {
    emit('deadline_control', ['blocked' => true, 'exception' => $exception::class, 'waits_ms' => $clock->waits]);
}
