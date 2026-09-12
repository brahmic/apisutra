<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\RemainingWork;

use Brahmic\ApiSutra\Auth\BearerAuthenticator;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Factory\RequestFactoryInterface;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Laravel\RequestFactory;
use Brahmic\ApiSutra\Laravel\SdkServiceProvider;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use Brahmic\ApiSutra\Tests\Stubs\Requests\DiRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

/** @param array<string, mixed> $data */
function emit(string $case, array $data): void
{
    echo json_encode(['case' => $case] + $data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

foreach (['none', 'fixed_bearer', 'refresh_ok', 'refresh_failed', 'refresh_untyped', 'refresh_ok_unbound'] as $case) {
    RefreshingAuthenticator::reset();
    $auth = match ($case) {
        'none' => null,
        'fixed_bearer' => new BearerAuthenticator('fixture-token'),
        default => new RefreshingAuthenticator(),
    };
    $mainCalls = 0;
    $transport = new MockTransport();
    $transport->fake(['*' => static function () use ($transport, $case, &$mainCalls): MockResponse {
        $records = $transport->getRecorded();
        $last = $records[array_key_last($records)];
        if (str_ends_with($last->url, '/refresh')) {
            return match ($case) {
                'refresh_failed' => MockResponse::make(['error' => 'refresh unavailable'], 503),
                'refresh_untyped' => MockResponse::make('', 204),
                default => MockResponse::success(['token' => 'fixture-new-token']),
            };
        }
        $mainCalls++;
        return str_starts_with($case, 'refresh_ok') && $mainCalls > 1
            ? MockResponse::success(['ok' => true])
            : MockResponse::make(['error' => 'original unauthorized'], 401, ['X-Fixture' => 'original']);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', auth: $auth, authRetryAttempts: 1), $transport);
    $request = new RetryPolicyRequest();
    if ($case !== 'refresh_ok_unbound') {
        $request->setClient($client);
    }
    $result = $client->send($request)->raw();
    emit('auth_' . $case, [
        'httpCalls' => count($transport->getRecorded()), 'mainCalls' => $mainCalls,
        'refreshRequests' => RefreshingAuthenticator::$refreshCalls,
        'code' => $result->errors->first()?->code->value, 'errorMessage' => $result->errors->first()?->message, 'responseStatus' => $result->response?->status,
        'responseBody' => $result->response?->body, 'originalHeader' => $result->response?->header('X-Fixture'),
    ]);
}

foreach ([true, false] as $httpContext) {
    $app = new Container();
    $factory = new RequestFactory();
    $registry = new ClientRegistry();
    $app->instance(RequestFactoryInterface::class, $factory);
    $app->instance(ClientRegistry::class, $registry);
    (new SdkServiceProvider($app))->register();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new MockTransport());
    $app->make(ClientRegistry::class)->register($client, 'Brahmic\\ApiSutra\\Tests\\Stubs\\Requests');
    if ($httpContext) {
        $app->instance(Request::class, Request::create('/di', 'GET', ['query' => 'incoming']));
    }
    $app->bind(DiRequest::class, static function () use ($client): DiRequest {
        $request = new DiRequest();
        $request->query = 'explicit';
        $request->setClient($client);
        return $request;
    });
    $request = $app->make(DiRequest::class);
    emit($httpContext ? 'laravel_incoming_overrides_explicit' : 'laravel_without_http', [
        'query' => $request->query,
        'customFactoryPreserved' => $app->make(RequestFactoryInterface::class) === $factory,
        'customRegistryPreserved' => $app->make(ClientRegistry::class) === $registry,
        'clientPreserved' => $request->getClient() === $client,
    ]);
}

foreach (['cursor_zero', 'cursor_cycle', 'cursor_failure', 'cursor_partial_failure'] as $case) {
    $transport = new MockTransport();
    $count = 0;
    $transport->fake(['*' => static function () use (&$count, $case): MockResponse {
        $count++;
        if (str_contains($case, 'failure') && $count > 1) {
            return MockResponse::make(['error' => 'page forbidden'], 403);
        }
        $next = match ($case) {
            'cursor_zero' => $count === 1 ? '0' : null,
            'cursor_cycle' => $count % 2 === 1 ? 'A' : 'B',
            default => 'A',
        };
        return MockResponse::success(['data' => [['id' => $count]], 'meta' => [
            'current_page' => $count, 'per_page' => 1, 'has_more' => $next !== null, 'next_cursor' => $next,
        ]]);
    }]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', authRetryOn401: false, paginationConfig: new PaginationConfig(maxPages: 5),
    ), $transport);
    $request = (new PaginatedRequest())->setClient($client);
    $paginator = $request->paginate();
    if ($case === 'cursor_partial_failure') {
        $paginator->failStrategy(FailStrategy::Partial);
    }
    $result = $paginator->all();
    emit($case, [
        'httpCalls' => count($transport->getRecorded()), 'urls' => array_map(static fn (PreparedRequest $r): string => $r->url, $transport->getRecorded()),
        'status' => $result->status->value, 'code' => $result->errors->first()?->code->value,
        'message' => $result->errors->first()?->message,
        'responseStatus' => $result->errors->first()?->response?->status,
    ]);
}

$options = RequestOptions::empty()->withRateLimit(2, 30)->withoutRateLimit();
emit('runtime_rate_limit_disable', ['disabled' => $options->getRateLimitDisabledOverride(), 'storedOverrideLimit' => $options->getRateLimitOverride()?->limit]);
$ttl = RequestOptions::empty()->withCache(10)->withoutCache()->withCache();
emit('runtime_cache_reenable', ['ttl' => $ttl->getCacheOverride()->ttl]);

foreach ([false, true] as $throw) {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make(['error' => 'forbidden'], 403)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', throwOnErrors: $throw), $transport);
    $batch = $client->batch([new RetryPolicyRequest()])->parallel()->send();
    $result = $batch->results()->all()[0];
    emit('parallel_batch_http_error', ['throwOnErrors' => $throw, 'code' => $result->errors->first()?->code->value,
        'responseStatus' => $result->response?->status, 'httpCalls' => count($transport->getRecorded())]);
}
