<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Auth\LockAwareAuthenticator;
use Brahmic\ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ProtectedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\RefreshTokenRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\FakeSleeper;
use Brahmic\ApiSutra\Tests\Support\LockingCache;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('RetrySender auth refresh', function () {
    it('выполняет refresh и повтор после 401', function () {
        RefreshingAuthenticator::reset();
        expect(RefreshingAuthenticator::$shouldRefresh)->toBeFalse();

        $transport = new MockTransport();
        $calls = 0;
        $transport->fake([
            ProtectedRequest::class => function () use (&$calls) {
                $calls++;
                if ($calls === 1) {
                    return MockResponse::make(['message' => 'Unauthorized'], 401);
                }

                return MockResponse::success(['id' => 1, 'name' => 'ok']);
            },
            RefreshTokenRequest::class => MockResponse::success(['token' => 'new-token']),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new RefreshingAuthenticator(),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $request = new ProtectedRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->status)->toBe(ResultStatus::SUCCESS);
        expect(RefreshingAuthenticator::$refreshCalls)->toBe(1);
        expect(RefreshingAuthenticator::$authenticateCalls)->toBeGreaterThanOrEqual(2);

        $client->assertSent(ProtectedRequest::class, null, 2);
        $client->assertSent(RefreshTokenRequest::class, null, 1);
    });

    it('повторяет refresh до authRetryAttempts при повторяющемся 401', function () {
        RefreshingAuthenticator::reset();

        $transport = new MockTransport();
        $calls = 0;
        $transport->fake([
            ProtectedRequest::class => function () use (&$calls) {
                $calls++;
                if ($calls <= 2) {
                    return MockResponse::make(['message' => 'Unauthorized'], 401);
                }

                return MockResponse::success(['id' => 1, 'name' => 'ok']);
            },
            RefreshTokenRequest::class => MockResponse::success(['token' => 'new-token']),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new RefreshingAuthenticator(),
            authRetryAttempts: 2,
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $request = new ProtectedRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->status)->toBe(ResultStatus::SUCCESS);
        expect(RefreshingAuthenticator::$refreshCalls)->toBe(2);

        $client->assertSent(ProtectedRequest::class, null, 3);
        $client->assertSent(RefreshTokenRequest::class, null, 2);
    });

    it('не делает refresh при authRetryAttempts=0', function () {
        RefreshingAuthenticator::reset();

        $transport = new MockTransport();
        $transport->fake([
            ProtectedRequest::class => MockResponse::make(['message' => 'Unauthorized'], 401),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new RefreshingAuthenticator(),
            authRetryAttempts: 0,
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $request = new ProtectedRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->isFailed())->toBeTrue();
        expect(RefreshingAuthenticator::$refreshCalls)->toBe(0);
        $client->assertSent(ProtectedRequest::class, null, 1);
    });

    it('останавливает 401-цикл при lock-timeout', function () {
        $cache = new LockingCache();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-timeout');
        $sleeper = new FakeSleeper();

        $transport = new MockTransport();
        $transport->fake([
            ProtectedRequest::class => MockResponse::make(['message' => 'Unauthorized'], 401),
            RefreshTokenRequest::class => MockResponse::success(['token' => 'new-token']),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            authRetryAttempts: 1,
            cache: new CacheConfig(store: $cache),
            timeout: 5,
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport, $sleeper);

        $request = new ProtectedRequest();
        $request->setClient($client);

        $lockKey = 'auth_refresh_lock:' . $auth->getCacheKey();
        $cache->set($lockKey, 'locked', 30);

        $result = $request->send()->raw();

        expect($result->isFailed())->toBeTrue();
        expect($auth->refreshCalls)->toBe(0);
        $client->assertSent(ProtectedRequest::class, null, 2);
        $client->assertSent(RefreshTokenRequest::class, null, 0);
        expect($sleeper->totalMs)->toBeGreaterThanOrEqual(5000);
    });

    it('не применяет общий retry для 401 даже если retryOn содержит 401', function () {
        RefreshingAuthenticator::reset();

        $transport = new MockTransport();
        $transport->fake([
            ProtectedRequest::class => MockResponse::make(['message' => 'Unauthorized'], 401),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new RefreshingAuthenticator(),
            authRetryAttempts: 0,
            retry: new RetryConfig(
                attempts: 3,
                baseDelay: 0,
                maxDelay: 0,
                backoff: BackoffStrategy::Constant,
                jitter: false,
                retryOn: [401],
            ),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $request = new ProtectedRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->isFailed())->toBeTrue();
        expect(RefreshingAuthenticator::$refreshCalls)->toBe(0);
        $client->assertSent(ProtectedRequest::class, null, 1);
    });

    it('совмещает refresh и retry при последующей ошибке', function () {
        RefreshingAuthenticator::reset();

        $transport = new MockTransport();
        $calls = 0;
        $transport->fake([
            ProtectedRequest::class => function () use (&$calls) {
                $calls++;
                return match ($calls) {
                    1 => MockResponse::make(['message' => 'Unauthorized'], 401),
                    2 => MockResponse::serverError(),
                    default => MockResponse::success(['id' => 1, 'name' => 'ok']),
                };
            },
            RefreshTokenRequest::class => MockResponse::success(['token' => 'new-token']),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: new RefreshingAuthenticator(),
            retry: new RetryConfig(
                attempts: 2,
                baseDelay: 0,
                maxDelay: 0,
                backoff: BackoffStrategy::Constant,
                jitter: false,
                retryOn: [500],
            ),
            environment: Environment::Testing,
        );
        $client = new TestClient($config, $transport);

        $request = new ProtectedRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->isSuccess())->toBeTrue();
        expect(RefreshingAuthenticator::$refreshCalls)->toBe(1);
        $client->assertSent(ProtectedRequest::class, null, 3);
        $client->assertSent(RefreshTokenRequest::class, null, 1);
    });
});
