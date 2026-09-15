<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Auth\TokenAuthenticator;
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Cache\CacheMode;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\CacheIdentity;
use Brahmic\ApiSutra\Tests\Stubs\Requests\AuthRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\TokenLoginRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\ClockCache;
use Brahmic\ApiSutra\Tests\Support\TestAuthLockProvider;
use Brahmic\ApiSutra\Tests\Support\VirtualClock;
use Brahmic\ApiSutra\Transport\MockTransport;

it('читает сохранённые старой версией HTTP и token записи без смены ключей', function (): void {
    $fixture = json_decode(file_get_contents(dirname(__DIR__, 2) . '/Fixtures/cache-before-store-separation.json'), true, flags: JSON_THROW_ON_ERROR);
    $clock = new VirtualClock();
    $clock->wallTime = $fixture['unixTime'];
    $store = new ClockCache($clock);
    $store->entries = $fixture['entries'];
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://migration.test',
        auth: new TokenAuthenticator('migration-user', 'synthetic-password', refreshRequestClass: TokenLoginRequest::class),
        cacheStore: $store,
        cacheConfig: new CacheConfig(ttl: 60, prefix: 'migration', identity: new CacheIdentity('tenant-1')),
    ), $transport, $clock, $clock);
    $dto = $client->send(new AuthRequest('migration'))->dataOrFail();
    expect($dto->id)->toBe(7)->and($dto->name)->toBe('baseline')->and($transport->getRecorded())->toBe([])
        ->and($store->entries)->toBe($fixture['entries']);
    // Новый HTTP использует сохранённый токен; refresh запрещён fake-транспортом.
    $transport->fake([AuthRequest::class => MockResponse::success(['id' => 8, 'name' => 'fresh'])]);
    expect((new AuthRequest('fresh'))->setClient($client)->withoutCache()->dataOrFail()->id)->toBe(8)
        ->and($transport->getRecorded()[0]->headers['Authorization'])->toBe('Bearer synthetic-migration-token');
    $transport->assertNotSent(TokenLoginRequest::class);
});

it('сохраняет store при копировании и меняет TTL только новых записей', function (bool $parameters): void {
    $clock = new VirtualClock();
    $store = new ClockCache($clock);
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheStore: $store, cacheConfig: $parameters ? new CacheConfig(ttl: 60) : null);
    $client = new TestClient($config, $transport, $clock, $clock);
    foreach ([$config, $config->with(), $config->with(timeout: 7)] as $cfg) {
        $copy = new TestClient($cfg, $transport, $clock, $clock);
        expect($copy->send(new CacheProbeRequest('/old'))->raw()->response->status)->toBe(200);
    }
    expect($transport->getRecorded())->toHaveCount(1);
    $short = new TestClient($config->with(cacheConfig: new CacheConfig(ttl: 10)), $transport, $clock, $clock);
    $short->send(new CacheProbeRequest('/new'))->dataOrFail();
    $clock->advance(11000);
    $short->send(new CacheProbeRequest('/new'))->dataOrFail();
    $short->send(new CacheProbeRequest('/old'))->dataOrFail();
    $client->send(new CacheProbeRequest('/original-new'))->dataOrFail();
    $clock->advance(11000);
    $client->send(new CacheProbeRequest('/original-new'))->dataOrFail();
    expect($transport->getRecorded())->toHaveCount(4);
})->with([false, true]);

it('сброс параметров после Disabled разрешает HTTP, сброс store запрещает даже Enabled override', function (): void {
    $clock = new VirtualClock();
    $store = new ClockCache($clock);
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheStore: $store, cacheConfig: new CacheConfig(mode: CacheMode::Disabled));
    $disabled = new TestClient($config, $transport);
    for ($i = 0; $i < 2; $i++) {
        $disabled->send(new CacheProbeRequest())->dataOrFail();
    }
    expect($transport->getRecorded())->toHaveCount(2);
    $enabled = new TestClient($config->with(cacheConfig: null), $transport);
    for ($i = 0; $i < 2; $i++) {
        $enabled->send(new CacheProbeRequest())->dataOrFail();
    }
    expect($transport->getRecorded())->toHaveCount(3);
    $before = $store->entries;
    $store->events = [];
    $removed = new TestClient($config->with(cacheStore: null)->with(cacheConfig: new CacheConfig()), $transport);
    for ($i = 0; $i < 2; $i++) {
        (new CacheProbeRequest())->setClient($removed)->withCache()->dataOrFail();
    }
    $removed->clearCache();
    (new CacheProbeRequest())->setClient($removed)->withCache()->clearCache();
    expect($transport->getRecorded())->toHaveCount(5)->and($store->events)->toBe([])->and($store->entries)->toBe($before);
    $enabled->send(new CacheProbeRequest())->dataOrFail();
    expect($transport->getRecorded())->toHaveCount(5);
});

it('разделяет store, параметры namespace и исходный долгоживущий клиент', function (): void {
    $clock = new VirtualClock();
    $first = new ClockCache($clock);
    $second = new ClockCache($clock);
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheStore: $first);
    $configs = [$config, $config->with(cacheStore: $second), $config->with(cacheConfig: new CacheConfig(prefix: 'different')),
        $config->with(cacheConfig: new CacheConfig(identity: new CacheIdentity('tenant-2')))];
    foreach ($configs as $cfg) {
        $client = new TestClient($cfg, $transport);
        $client->send(new CacheProbeRequest())->dataOrFail();
        $client->send(new CacheProbeRequest())->dataOrFail();
    }
    expect($transport->getRecorded())->toHaveCount(4);
    $original = new TestClient($config, $transport);
    $original->send(new CacheProbeRequest())->dataOrFail();
    expect($transport->getRecorded())->toHaveCount(4);
    $original->clearCache();
    $original->send(new CacheProbeRequest())->dataOrFail();
    expect($transport->getRecorded())->toHaveCount(5)->and($second->entries)->not->toBe([]);
});

it('общий auth store переживает смену timeout и HTTP mode, а отключение store сохраняет явный locks', function (bool $parameters): void {
    $clock = new VirtualClock();
    $store = new ClockCache($clock);
    $locks = new TestAuthLockProvider($clock);
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([
        AuthRequest::class => MockResponse::success(['id' => 7, 'name' => 'auth']),
        TokenLoginRequest::class => MockResponse::success(['accessToken' => 'synthetic-token', 'expiresIn' => 600]),
    ]);
    $config = new ClientConfig(
        baseUrl: 'https://copy.test',
        cacheStore: $store,
        cacheConfig: $parameters ? new CacheConfig(mode: CacheMode::Disabled, locks: $locks) : null,
        auth: new TokenAuthenticator('synthetic-user', 'synthetic-password', refreshRequestClass: TokenLoginRequest::class)
    );
    foreach ([$config, $config->with(timeout: 7), $config->with(cacheConfig: new CacheConfig(mode: CacheMode::ReadOnly))] as $cfg) {
        $client = new TestClient($cfg, $transport, $clock, $clock);
        (new AuthRequest('value'))->setClient($client)->withoutCache()->dataOrFail();
    }
    $transport->assertSent(TokenLoginRequest::class, times: 1);
    $snapshot = $store->entries;
    $store->events = [];
    $removed = new TestClient($config->with(cacheStore: null), $transport, $clock, $clock);
    (new AuthRequest('value'))->setClient($removed)->withoutCache()->dataOrFail();
    $transport->assertSent(TokenLoginRequest::class, times: 2);
    expect($store->events)->toBe([])->and($store->entries)->toBe($snapshot);
    if ($parameters) {
        expect($locks->keys)->toHaveCount(2)->and($locks->releases)->toBe(2);
    }
})->with([false, true]);
