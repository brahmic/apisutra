<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Exceptions\Auth\AuthRefreshLockTimeoutException;
use Brahmic\ApiSutra\Exceptions\Auth\AuthDependencyException;
use Brahmic\ApiSutra\Pipeline\Auth\AuthHandler;
use Brahmic\ApiSutra\Tests\Stubs\Auth\LockAwareAuthenticator;
use Brahmic\ApiSutra\Tests\Stubs\Dto\TokenResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\Requests\AuthRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\FakeSleeper;
use Brahmic\ApiSutra\Tests\Support\RecordingPipelineExecutor;
use Brahmic\ApiSutra\Tests\Support\TestAuthLockProvider;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('AuthHandler refresh lock', function () {
    it('не делает refresh при занятых lock и отсутствии потребности', function () {
        $locks = new TestAuthLockProvider();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-1', stopAfterFirstCheck: true);
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cacheConfig: new CacheConfig(locks: $locks),
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

        $locks->busy = true;

        $handler->handleAuthentication($request, $context);

        expect($locks->keys)->toBe([])
            ->and($auth->refreshCalls)->toBe(0)
            ->and($executor->calls)->toBe(0);
    });

    it('освобождает lock после успешного refresh', function () {
        $locks = new TestAuthLockProvider();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-2');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cacheConfig: new CacheConfig(locks: $locks),
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

        expect($locks->keys)->toHaveCount(1)
            ->and($locks->releases)->toBe(1)
            ->and($auth->refreshCalls)->toBe(1)
            ->and($executor->calls)->toBe(1);
    });

    it('ждет lock до тайм-аута и прекращает авторизацию', function () {
        $locks = new TestAuthLockProvider();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-timeout');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cacheConfig: new CacheConfig(locks: $locks),
            environment: Environment::Testing,
        );

        $executor = new RecordingPipelineExecutor(new TokenResponseDto('new-token'));
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $executor, $sleeper);

        $locks->busy = true;

        $request = new AuthRequest('payload');
        $context = new PipelineContext($request, $config, 'trace-lock');
        expect(fn () => $handler->handleAuthentication($request, $context))->toThrow(AuthRefreshLockTimeoutException::class);

        expect($executor->calls)->toBe(0)
            ->and($sleeper->calls)->toBeGreaterThan(0)
            ->and($sleeper->totalMs)->toBeGreaterThanOrEqual(1000);
    });

    it('force refresh не проверяет shouldRefresh при ожидании лока', function () {
        $locks = new TestAuthLockProvider();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-force-wait');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cacheConfig: new CacheConfig(locks: $locks),
            environment: Environment::Testing,
        );

        $executor = new RecordingPipelineExecutor(new TokenResponseDto('new-token'));
        $sleeper = new FakeSleeper();
        $handler = new AuthHandler($config, $executor, $sleeper);

        $locks->busy = true;

        $request = new AuthRequest('payload');
        $context = new PipelineContext($request, $config, 'trace-lock');
        expect(fn () => $handler->handleAuthentication($request, $context, true))->toThrow(AuthRefreshLockTimeoutException::class);

        expect($auth->shouldRefreshCalls)->toBe(0)
            ->and($sleeper->calls)->toBeGreaterThan(0)
            ->and($executor->calls)->toBe(0);
    });

    it('force refresh выполняет refresh даже при shouldRefresh=false', function () {
        $locks = new TestAuthLockProvider();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-force');
        $auth->processTokenResponse(new TokenResponseDto('current-token'));
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            cacheConfig: new CacheConfig(locks: $locks),
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
        $locks = new TestAuthLockProvider();
        $auth = new LockAwareAuthenticator(cacheKey: 'auth-lock-fail');
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            auth: $auth,
            authRetryAttempts: 1,
            cacheConfig: new CacheConfig(locks: $locks),
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
            ->toThrow(AuthDependencyException::class)
            ->and($executor->calls)->toBe(1);
    });
});
