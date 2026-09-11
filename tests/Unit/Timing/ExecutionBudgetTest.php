<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Hooks\Hook;
use Brahmic\ApiSutra\Exceptions\Transport\ConnectionException;
use Brahmic\ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Brahmic\ApiSutra\Exceptions\Transport\TimeoutException;
use Brahmic\ApiSutra\Execution\BatchExecutor;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Auth\LockAwareAuthenticator;
use Brahmic\ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CompositeAggregateRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\DependsOnMainRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\ArrayCache;
use Brahmic\ApiSutra\Tests\Support\LockingCache;
use Brahmic\ApiSutra\Tests\Support\VirtualClock;
use Brahmic\ApiSutra\Timing\ExecutionBudget;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\TransportOptions;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

beforeEach(fn () => RefreshingAuthenticator::reset());
afterEach(fn () => RefreshingAuthenticator::reset());

it('использует монотонный срок и позволяет дочернему бюджету только сократить его', function (): void {
    $clock = new VirtualClock();
    $parent = new ExecutionBudget($clock, 1000);
    $clock->advance(200);
    $clock->wallTime += 3600;
    $inherited = new ExecutionBudget(new VirtualClock(), 5000, $parent);
    $shorter = new ExecutionBudget($clock, 100, $parent);
    expect($parent->remainingMs())->toBe(800)->and($inherited->remainingMs())->toBe(800)
        ->and($shorter->remainingMs())->toBe(100);
    $clock->wallTime -= 7200;
    $clock->advance(100);
    expect(fn () => (new TransportOptions(0, 0, $shorter))->effective())->toThrow(ExecutionDeadlineException::class);
    expect((new TransportOptions(30000, 10000, $inherited))->effective()->timeoutMs)->toBe(700);
});

it('refresh наследует остаток при начальной авторизации и после 401', function (bool $initial): void {
    $clock = new VirtualClock();
    RefreshingAuthenticator::$shouldRefresh = $initial;
    $transport = new MockTransport();
    $remaining = null;
    $transport->fake(['*' => static function () use ($clock, &$remaining, $initial, $transport): MockResponse {
        $requests = $transport->getRecorded();
        $request = $requests[array_key_last($requests)];
        if (str_ends_with($request->url, '/refresh')) {
            $remaining = $request->transportOptions->effective()->timeoutMs;
            $clock->advance($initial ? 1001 : 751);
            return MockResponse::success(['token' => 'fixture-token']);
        }
        return MockResponse::make('Unauthorized', 401);
    }]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', auth: new RefreshingAuthenticator(),
        retry: new RetryConfig(totalTimeoutMs: 1000),
    ), $transport, $clock, $clock);
    $client->hooks()->on(Hook::AfterResponse, new class($clock) implements HookInterface {
        public function __construct(private VirtualClock $clock) {}
        public function handle(PipelineContext $context): ?array
        {
            if ($context->parent === null) {
                $this->clock->advance(250);
            }
            return null;
        }
    });
    $result = (new RetryPolicyRequest())->setClient($client)->send()->raw();
    expect($result->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
        ->and($remaining)->toBe($initial ? 1000 : 750)
        ->and($transport->getRecorded())->toHaveCount($initial ? 1 : 2)
        ->and(RefreshingAuthenticator::$refreshCalls)->toBe(1);
})->with([false, true]);

it('не ждёт auth lock дольше остатка и не начинает refresh', function (): void {
    $clock = new VirtualClock();
    $cache = new LockingCache();
    $cache->set('auth_refresh_lock:fixture-lock', 'other-owner');
    $auth = new LockAwareAuthenticator(cacheKey: 'fixture-lock');
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', auth: $auth, cache: new CacheConfig(store: $cache),
        retry: new RetryConfig(totalTimeoutMs: 125),
    ), $transport, $clock, $clock);
    $result = (new RetryPolicyRequest())->setClient($client)->send()->raw();
    expect($result->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
        ->and($result->exception->stage)->toBe('auth_lock_wait')
        ->and($clock->waits)->toBe([50, 50])->and($transport->getRecorded())->toBe([])
        ->and($auth->refreshCalls)->toBe(0)->and($cache->get('auth_refresh_lock:fixture-lock'))->toBe('other-owner');
});

it('поздний hook не кеширует успех и новое выполнение получает новый бюджет', function (bool $throws): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', cache: new CacheConfig(store: new ArrayCache()),
        retry: new RetryConfig(totalTimeoutMs: 1000),
    ), $transport, $clock, $clock);
    $client->hooks()->on(Hook::AfterHydrate, new class($clock, $throws) implements HookInterface {
        public function __construct(private VirtualClock $clock, private bool $throws) {}
        public function handle(PipelineContext $context): ?array
        {
            $this->clock->advance(1001);
            if ($this->throws) {
                throw new RuntimeException('fixture late hook');
            }
            return null;
        }
    });
    $request = (new CacheProbeRequest())->setClient($client);
    $result = $request->send()->raw();
    expect($result->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
        ->and($result->response->status)->toBe(200);
    if ($throws) {
        expect($result->exception->getPrevious())->not->toBeNull();
    }
    $client->hooks()->remove(Hook::AfterHydrate);
    expect($request->send()->raw()->isSuccess())->toBeTrue()
        ->and($request->send()->raw()->isSuccess())->toBeTrue()
        ->and($transport->getRecorded())->toHaveCount(2);
})->with([false, true]);

it('сохраняет сетевую причину когда backoff не помещается', function (): void {
    $clock = new VirtualClock();
    $cause = new ConnectionException('fixture connection');
    $transport = new MockTransport();
    $transport->fake(['*' => static function () use ($cause): never { throw $cause; }]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test',
        retry: new RetryConfig(baseDelay: 2000, jitter: false, totalTimeoutMs: 1000),
    ), $transport, $clock, $clock);
    $result = (new RetryPolicyRequest())->setClient($client)->send()->raw();
    expect($result->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
        ->and($result->exception->getPrevious())->toBe($cause)
        ->and($transport->getRecorded())->toHaveCount(1)->and($clock->waits)->toBe([]);
});

it('таймаут отдельной попытки допускает безопасный retry в оставшемся бюджете', function (): void {
    $clock = new VirtualClock();
    $calls = 0;
    $transport = new MockTransport();
    $transport->fake(['*' => static function () use ($clock, &$calls): MockResponse {
        $calls++;
        $clock->advance(100);
        if ($calls === 1) {
            throw new TimeoutException('fixture attempt timeout');
        }
        return MockResponse::success();
    }]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', retry: new RetryConfig(baseDelay: 0, jitter: false, totalTimeoutMs: 1000),
    ), $transport, $clock, $clock);
    expect((new RetryPolicyRequest())->setClient($client)->send()->raw()->isSuccess())->toBeTrue()->and($calls)->toBe(2);
});

it('сохраняет смысл deadline в dataOrFail и throwOnErrors', function (bool $throwOnErrors): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', delay: 2000, throwOnErrors: $throwOnErrors,
        retry: new RetryConfig(attempts: 1, totalTimeoutMs: 1000),
    ), $transport, $clock, $clock);
    expect(fn () => (new RetryPolicyRequest())->setClient($client)->send()->dataOrFail())->toThrow(ExecutionDeadlineException::class);
    expect($transport->getRecorded())->toBe([]);
})->with([false, true]);

it('batch items наследуют переданный deadline в обоих режимах', function (ExecutionMode $mode, bool $throwOnErrors): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $remaining = null;
    $transport->fake(['*' => static function () use ($clock, $transport, &$remaining): MockResponse {
        $remaining = $transport->getRecorded()[0]->transportOptions->effective()->timeoutMs;
        $clock->advance(251);
        return MockResponse::success();
    }]);
    $config = new ClientConfig(baseUrl: 'https://fixture.test', throwOnErrors: $throwOnErrors, retry: new RetryConfig(totalTimeoutMs: 5000));
    $client = new TestClient($config, $transport, $clock, $clock);
    $request = (new RetryPolicyRequest())->setClient($client);
    $parent = new PipelineContext($request, $config, 'fixture-parent');
    $parent->budget = new ExecutionBudget($clock, 1000);
    $clock->advance(750);
    $batch = new BatchExecutor($client, [$request, $request], mode: $mode, parent: $parent);
    if ($throwOnErrors && $mode === ExecutionMode::Sequential) {
        expect(fn () => $batch->execute())->toThrow(ExecutionDeadlineException::class);
    } else {
        $results = $batch->execute();
        expect($results[0]->errors->first()->code->value)->toBe('timeout')
            ->and($results[0]->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
            ->and($results[0]->response->status)->toBe(200);
    }
    expect($remaining)->toBe(250)->and($transport->getRecorded())->toHaveCount(1);
})->with([ExecutionMode::Sequential, ExecutionMode::Parallel])->with([false, true]);

it('depends-on и composite не перезапускают бюджет между дочерними отправками', function (string $requestClass): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $remaining = [];
    $transport->fake(['*' => static function () use ($clock, $transport, &$remaining): MockResponse {
        $recorded = $transport->getRecorded();
        $remaining[] = $recorded[array_key_last($recorded)]->transportOptions->effective()->timeoutMs;
        $clock->advance(600);
        return MockResponse::success(['id' => 1, 'name' => 'fixture', 'token' => 'fixture-token']);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 1, totalTimeoutMs: 1000)), $transport, $clock, $clock);
    $result = (new $requestClass())->setClient($client)->send()->raw();
    expect($result->exception)->toBeInstanceOf(ExecutionDeadlineException::class)->and($remaining)->toBe([1000, 400]);
})->with([DependsOnMainRequest::class, CompositeAggregateRequest::class]);
