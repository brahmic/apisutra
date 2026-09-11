<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Exceptions\Request\UnauthorizedException;
use Brahmic\ApiSutra\Pipeline\Auth\AuthHandler;
use Brahmic\ApiSutra\Tests\Stubs\Auth\LockAwareAuthenticator;
use Brahmic\ApiSutra\Tests\Stubs\Dto\TokenResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\Requests\AuthRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\FakeSleeper;
use Brahmic\ApiSutra\Tests\Support\LockingCache;
use Brahmic\ApiSutra\Tests\Support\RecordingPipelineExecutor;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('AuthHandler refresh lock', function () {
    it('не делает refresh при занятых lock и отсутствии потребности', function () {
        $cache = new LockingCache();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-1', stopAfterFirstCheck: true);
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cache: new CacheConfig(store: $cache),
            environment: Environment::Testing,
        );

        $executor = new RecordingPipelineExecutor(new TokenResponseDto('new-token'));
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $executor, $sleeper);

        $transport = new MockTransport();
        $client = new TestClient($config, $transport);

        $request = new AuthRequest('payload');
        $request->setClient($client);

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace-1',
            preparedRequest: new PreparedRequest(HttpMethod::GET, 'https://api.test/auth'),
        );

        $lockKey = 'auth_refresh_lock:' . $auth->getCacheKey();
        $cache->set($lockKey, 'locked', 30);
        $cache->lastAddKey = null;

        $handler->handleAuthentication($request, $context);

        expect($cache->lastAddKey)->toBe($lockKey)
            ->and($auth->refreshCalls)->toBe(0)
            ->and($executor->calls)->toBe(0);
    });

    it('освобождает lock после успешного refresh', function () {
        $cache = new LockingCache();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-2');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cache: new CacheConfig(store: $cache),
            environment: Environment::Testing,
        );

        $executor = new RecordingPipelineExecutor(new TokenResponseDto('new-token'));
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $executor, $sleeper);

        $transport = new MockTransport();
        $client = new TestClient($config, $transport);

        $request = new AuthRequest('payload');
        $request->setClient($client);

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace-2',
            preparedRequest: new PreparedRequest(HttpMethod::GET, 'https://api.test/auth'),
        );

        $handler->handleAuthentication($request, $context);

        $lockKey = 'auth_refresh_lock:' . $auth->getCacheKey();
        expect($cache->lastAddKey)->toBe($lockKey)
            ->and($cache->has($lockKey))->toBeFalse()
            ->and($auth->refreshCalls)->toBe(1)
            ->and($executor->calls)->toBe(1);
    });

    it('ждет lock до тайм-аута и возвращает null', function () {
        $cache = new LockingCache();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-timeout');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cache: new CacheConfig(store: $cache),
            environment: Environment::Testing,
        );

        $executor = new RecordingPipelineExecutor(new TokenResponseDto('new-token'));
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $executor, $sleeper);

        $lockKey = 'auth_refresh_lock:' . $auth->getCacheKey();
        $cache->set($lockKey, 'locked', 30);

        $method = new ReflectionMethod(AuthHandler::class, 'waitForRefreshLock');
        $method->setAccessible(true);

        $result = $method->invoke($handler, $auth, $lockKey, 1, false);

        expect($result)->toBeNull()
            ->and($sleeper->calls)->toBeGreaterThan(0)
            ->and($sleeper->totalMs)->toBeGreaterThanOrEqual(1000);
    });

    it('force refresh не проверяет shouldRefresh при ожидании лока', function () {
        $cache = new LockingCache();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-force-wait');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cache: new CacheConfig(store: $cache),
            environment: Environment::Testing,
        );

        $executor = new RecordingPipelineExecutor(new TokenResponseDto('new-token'));
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $executor, $sleeper);

        $lockKey = 'auth_refresh_lock:' . $auth->getCacheKey();
        $cache->set($lockKey, 'locked', 30);

        $method = new ReflectionMethod(AuthHandler::class, 'waitForRefreshLock');
        $method->setAccessible(true);

        $result = $method->invoke($handler, $auth, $lockKey, 1, true);

        expect($auth->shouldRefreshCalls)->toBe(0)
            ->and($sleeper->calls)->toBeGreaterThan(0)
            ->and($result)->toBeNull();
    });

    it('force refresh выполняет refresh даже при shouldRefresh=false', function () {
        $cache = new LockingCache();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-force');
        $auth->processTokenResponse(new TokenResponseDto('current-token'));
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cache: new CacheConfig(store: $cache),
            environment: Environment::Testing,
        );

        $executor = new RecordingPipelineExecutor(new TokenResponseDto('new-token'));
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $executor, $sleeper);

        $transport = new MockTransport();
        $client = new TestClient($config, $transport);

        $request = new AuthRequest('payload');
        $request->setClient($client);

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace-force',
            preparedRequest: new PreparedRequest(HttpMethod::GET, 'https://api.test/auth'),
        );

        $handler->handleAuthentication($request, $context, true);

        expect($auth->shouldRefreshCalls)->toBe(0)
            ->and($auth->refreshCalls)->toBe(1)
            ->and($executor->calls)->toBe(1);
    });

    it('выбрасывает UnauthorizedException при неуспешном refresh', function () {
        $cache = new LockingCache();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-fail');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            authRetryAttempts: 1,
            cache: new CacheConfig(store: $cache),
            environment: Environment::Testing,
        );

        $executor = new RecordingPipelineExecutor(status: ResultStatus::FAILED);
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $executor, $sleeper);

        $transport = new MockTransport();
        $client = new TestClient($config, $transport);

        $request = new AuthRequest('payload');
        $request->setClient($client);

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace-fail',
            preparedRequest: new PreparedRequest(HttpMethod::GET, 'https://api.test/auth'),
        );

        expect(fn() => $handler->handleAuthentication($request, $context))
            ->toThrow(UnauthorizedException::class)
            ->and($executor->calls)->toBe(1);
    });
});
