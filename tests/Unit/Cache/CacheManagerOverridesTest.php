<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Cache\CacheMode;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Enums\Http\QueryArrayFormat;
use Brahmic\ApiSutra\Pipeline\Cache\CacheManager;
use Brahmic\ApiSutra\Pipeline\Preparation\PreparedRequestFactory;
use Brahmic\ApiSutra\Pipeline\Preparation\RequestPreparer;
use Brahmic\ApiSutra\Serialization\Serializer;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\AttributeRichRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CacheableRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CacheDisabledRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\SpyCache;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

describe('CacheManager overrides', function () {
    it('withoutCache отключает чтение и запись', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::sequence([
                MockResponse::make(['value' => 1]),
                MockResponse::make(['value' => 2]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $first = $request->withoutCache()->send()->raw();
        $second = $request->withoutCache()->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 2])
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($cache->lastSetKey)->toBeNull();
    });

    it('withCacheWriteOnly пишет в кеш без чтения', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::sequence([
                MockResponse::make(['value' => 1]),
                MockResponse::make(['value' => 2]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $first = $request->withCacheWriteOnly()->send()->raw();
        $second = $request->withCacheWriteOnly()->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 2])
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($cache->lastSetKey)->not->toBeNull();
    });

    it('withCache применяет ttl override', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $request->withCache(15)->send()->raw();

        expect($cache->lastSetTtl)->toBe(15);
    });

    it('withCache использует ttl из атрибута при null override', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $request->withCache()->send()->raw();

        expect($cache->lastSetTtl)->toBe(120);
    });

    it('withCache использует ttl из конфига при отсутствии атрибута', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'User']),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 45),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $request->withCache()->send()->raw();

        expect($cache->lastSetTtl)->toBe(45);
    });

    it('атрибут Cache(mode=Disabled) отключает кеш', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheDisabledRequest::class => MockResponse::sequence([
                MockResponse::success(['value' => 1]),
                MockResponse::success(['value' => 2]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheDisabledRequest('payload');
        $request->setClient($client);

        $first = $request->send()->raw();
        $second = $request->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 2])
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($cache->lastSetKey)->toBeNull();
    });

    it('withCache включает кеш при Cache(mode=Disabled)', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheDisabledRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheDisabledRequest('payload');
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $second = $request->withCache()->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 1])
            ->and($transport->getRecorded())->toHaveCount(1)
            ->and($cache->lastSetKey)->not->toBeNull();
    });

    it('withCache включает кеш при глобальном отключении', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60, mode: CacheMode::Disabled),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $second = $request->withCache()->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 1])
            ->and($transport->getRecorded())->toHaveCount(1);
    });

    it('baseUrl override влияет на cache key', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::sequence([
                MockResponse::success(['value' => 1]),
                MockResponse::success(['value' => 2]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $firstKey = $cache->lastSetKey;

        $second = $request->withCache()->withBaseUrl('https://api.alt')->send()->raw();
        $secondKey = $cache->lastSetKey;

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 2])
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($firstKey)->not->toBe($secondKey);
    });

    it('использует key из cache-атрибута', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            AttributeRichRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60, prefix: 'prefix:'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $request = new AttributeRichRequest('payload');
        $request->setClient($client);

        $request->withIdempotencyKey('id')->withCache()->send()->raw();

        expect($cache->lastSetKey)->toBe('prefix:attr-cache-key');
    });

    it('cache key стабилен при разном порядке параметров', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $cacheManager = new CacheManager(
            $config,
            new PreparedRequestFactory(new Serializer(new CastRegistry()), new RequestPreparer($config)),
            new RequestPreparer($config),
        );

        $request = new SimpleGetRequest('q');
        $preparedA = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [
                'query' => [
                    'b' => ['value' => '2', 'format' => null],
                    'a' => ['value' => '1', 'format' => null],
                ],
            ],
        );
        $preparedB = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [
                'query' => [
                    'a' => ['value' => '1', 'format' => null],
                    'b' => ['value' => '2', 'format' => null],
                ],
            ],
        );

        $method = new ReflectionMethod(CacheManager::class, 'buildCacheKey');
        $method->setAccessible(true);

        $keyA = $method->invoke($cacheManager, $preparedA, 'prefix:', $request);
        $keyB = $method->invoke($cacheManager, $preparedB, 'prefix:', $request);

        expect($keyA)->toBe($keyB);
    });

    it('cache key стабилен при разном порядке значений массива', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $cacheManager = new CacheManager(
            $config,
            new PreparedRequestFactory(new Serializer(new CastRegistry()), new RequestPreparer($config)),
            new RequestPreparer($config),
        );

        $request = new SimpleGetRequest('q');
        $preparedA = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [
                'query' => [
                    'ids' => ['value' => [2, 1], 'format' => null],
                ],
            ],
        );
        $preparedB = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [
                'query' => [
                    'ids' => ['value' => [1, 2], 'format' => null],
                ],
            ],
        );

        $method = new ReflectionMethod(CacheManager::class, 'buildCacheKey');
        $method->setAccessible(true);

        $keyA = $method->invoke($cacheManager, $preparedA, 'prefix:', $request);
        $keyB = $method->invoke($cacheManager, $preparedB, 'prefix:', $request);

        expect($keyA)->toBe($keyB);
    });

    it('cache key учитывает формат массива в query', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $cacheManager = new CacheManager(
            $config,
            new PreparedRequestFactory(new Serializer(new CastRegistry()), new RequestPreparer($config)),
            new RequestPreparer($config),
        );

        $request = new SimpleGetRequest('q');
        $preparedA = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [
                'query' => [
                    'ids' => ['value' => [1, 2], 'format' => QueryArrayFormat::Comma],
                ],
            ],
        );
        $preparedB = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [
                'query' => [
                    'ids' => ['value' => [1, 2], 'format' => QueryArrayFormat::Repeat],
                ],
            ],
        );

        $method = new ReflectionMethod(CacheManager::class, 'buildCacheKey');
        $method->setAccessible(true);

        $keyA = $method->invoke($cacheManager, $preparedA, 'prefix:', $request);
        $keyB = $method->invoke($cacheManager, $preparedB, 'prefix:', $request);

        expect($keyA)->not->toBe($keyB);
    });

    it('cache key учитывает http метод', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $cacheManager = new CacheManager(
            $config,
            new PreparedRequestFactory(new Serializer(new CastRegistry()), new RequestPreparer($config)),
            new RequestPreparer($config),
        );

        $request = new SimpleGetRequest('q');
        $preparedA = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/items',
            meta: [],
        );
        $preparedB = new PreparedRequest(
            method: HttpMethod::POST,
            url: 'https://api.test/items',
            meta: [],
        );

        $method = new ReflectionMethod(CacheManager::class, 'buildCacheKey');
        $method->setAccessible(true);

        $keyA = $method->invoke($cacheManager, $preparedA, 'prefix:', $request);
        $keyB = $method->invoke($cacheManager, $preparedB, 'prefix:', $request);

        expect($keyA)->not->toBe($keyB);
    });

    it('cache key учитывает тело запроса', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $cacheManager = new CacheManager(
            $config,
            new PreparedRequestFactory(new Serializer(new CastRegistry()), new RequestPreparer($config)),
            new RequestPreparer($config),
        );

        $request = new SimpleGetRequest('q');
        $preparedA = new PreparedRequest(
            method: HttpMethod::POST,
            url: 'https://api.test/items',
            body: json_encode(['a' => 1], JSON_UNESCAPED_UNICODE),
            meta: [],
        );
        $preparedB = new PreparedRequest(
            method: HttpMethod::POST,
            url: 'https://api.test/items',
            body: json_encode(['a' => 2], JSON_UNESCAPED_UNICODE),
            meta: [],
        );

        $method = new ReflectionMethod(CacheManager::class, 'buildCacheKey');
        $method->setAccessible(true);

        $keyA = $method->invoke($cacheManager, $preparedA, 'prefix:', $request);
        $keyB = $method->invoke($cacheManager, $preparedB, 'prefix:', $request);

        expect($keyA)->not->toBe($keyB);
    });

    it('page и limit влияют на cache key', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::success(['data' => []]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);
        $request = new PaginatedRequest();
        $request->setClient($client);

        $request->withCache(300)->withPage(1)->withLimit(10)->send()->raw();
        $firstKey = $cache->lastSetKey;

        $request->withCache(300)->withPage(2)->withLimit(10)->send()->raw();
        $secondKey = $cache->lastSetKey;

        expect($firstKey)->not->toBeNull()
            ->and($secondKey)->not->toBeNull()
            ->and($firstKey)->not->toBe($secondKey);
    });

    it('clearCache удаляет запись по ключу', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            CacheableRequest::class => MockResponse::success(['value' => 1]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);
        $request = new CacheableRequest('payload');
        $request->setClient($client);

        $request->withCache()->send()->raw();
        $setKey = $cache->lastSetKey;

        $request->clearCache();

        expect($setKey)->not->toBeNull()
            ->and($cache->lastDeleteKey)->toBe($setKey);
    });

    it('clearCache учитывает pagination options', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::success(['data' => []]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);
        $request = new PaginatedRequest();
        $request->setClient($client);

        $execution = $request->withCache(300)->withPage(2)->withLimit(10);
        $execution->send()->raw();
        $setKey = $cache->lastSetKey;

        $execution->clearCache();

        expect($setKey)->not->toBeNull()
            ->and($cache->lastDeleteKey)->toBe($setKey);
    });
});
