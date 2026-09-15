<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Auth\TokenAuthenticator;
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\CacheIdentity;
use Brahmic\ApiSutra\Tests\Stubs\Requests\AuthRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\TokenLoginRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\ClockCache;
use Brahmic\ApiSutra\Tests\Support\VirtualClock;
use Brahmic\ApiSutra\Transport\MockTransport;

require getcwd() . '/vendor/autoload.php';
$clock = new VirtualClock();
$store = new ClockCache($clock);
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake([
    TokenLoginRequest::class => MockResponse::success(['accessToken' => 'synthetic-migration-token', 'expiresIn' => 600]),
    AuthRequest::class => MockResponse::success(['id' => 7, 'name' => 'baseline']),
]);
$client = new TestClient(new ClientConfig(
    baseUrl: 'https://migration.test',
    auth: new TokenAuthenticator('migration-user', 'synthetic-password', refreshRequestClass: TokenLoginRequest::class),
    cache: new CacheConfig(store: $store, ttl: 60, prefix: 'migration', identity: new CacheIdentity('tenant-1')),
), $transport, $clock, $clock);
$first = $client->send(new AuthRequest('migration'))->dataOrFail();
$second = $client->send(new AuthRequest('migration'))->dataOrFail();
if ($first->id !== 7 || $second->id !== 7 || count($transport->getRecorded()) !== 2) {
    throw new LogicException('Не удалось получить исходный cache hit');
}
echo json_encode(['unixTime' => $clock->unixTime(), 'entries' => $store->entries, 'httpCount' => 2], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
