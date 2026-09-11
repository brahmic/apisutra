<?php

declare(strict_types=1);

use Acme\Discovery\FlowClient;
use Acme\Discovery\Requests\DiscoveryDtoRequest;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;
use Brahmic\ApiSutra\Pagination\PaginationRule;
use Brahmic\ApiSutra\Resolver\ClassMapProvider;
use Brahmic\ApiSutra\Resolver\ClientDiscoveryCache;
use Brahmic\ApiSutra\Resolver\ClientDiscoveryService;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Brahmic\ApiSutra\Resolver\ClientResolver;
use Brahmic\ApiSutra\Resolver\DiscoveryOptions;
use Brahmic\ApiSutra\Resolver\RequestNamespaceDetector;
use Brahmic\ApiSutra\Resolver\RequestScanner;
use Brahmic\ApiSutra\Result\PaginatedResult;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Core flow integration', function () {
    it('happy‑path проходит полный flow', function () {
        $transport = new MockTransport();
        $transport->fake([
            DiscoveryDtoRequest::class => MockResponse::success(['id' => 1, 'name' => 'User']),
        ]);

        $client = new FlowClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache();
        $service = new ClientDiscoveryService($registry, $detector, $cache);
        $service->registerAuto($client, DiscoveryOptions::forceOff());

        $resolver = new ClientResolver($registry);
        $request = new DiscoveryDtoRequest();
        $request->setClient($resolver->resolve($request));

        $handle = $request->send();
        $result = $handle->raw();

        expect($result->isSuccess())->toBeTrue();
        expect($result->data)->toBeInstanceOf(SimpleResponseDto::class);
        expect($result->data->id)->toBe(1);

        $resolved = $handle->resolved();
        expect($resolved->isSuccess())->toBeTrue();
        expect($resolved->data())->toBeInstanceOf(SimpleResponseDto::class);
    });

    it('error‑path корректно обрабатывает retry и результат', function () {
        $transport = new MockTransport();
        $transport->fake([
            DiscoveryDtoRequest::class => MockResponse::sequence([
                MockResponse::serverError(),
                MockResponse::serverError(),
            ]),
        ]);

        $client = new FlowClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                retry: new RetryConfig(
                    attempts: 2,
                    baseDelay: 0,
                    maxDelay: 0,
                    backoff: BackoffStrategy::Constant,
                    jitter: false,
                    retryOn: [500],
                ),
                environment: Environment::Testing,
            ),
            $transport,
        );

        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache();
        $service = new ClientDiscoveryService($registry, $detector, $cache);
        $service->registerAuto($client, DiscoveryOptions::forceOff());

        $resolver = new ClientResolver($registry);
        $request = new DiscoveryDtoRequest();
        $request->setClient($resolver->resolve($request));

        $handle = $request->send();
        $result = $handle->raw();

        expect($result->isFailed())->toBeTrue();
        expect($result->errors->first()?->code)->toBe(ErrorCode::ServerError);
        expect($transport->getRecorded())->toHaveCount(2);

        $resolved = $handle->resolved();
        expect($resolved->isFailed())->toBeTrue();
    });

    it('pagination‑flow интеграция', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::sequence([
                MockResponse::success([
                    'data' => [1],
                    'meta' => [
                        'page' => 1,
                        'per_page' => 1,
                        'total' => 2,
                        'has_more' => true,
                        'next_cursor' => 'page-2',
                    ],
                ]),
                MockResponse::success([
                    'data' => [2],
                    'meta' => [
                        'page' => 2,
                        'per_page' => 1,
                        'total' => 2,
                        'has_more' => false,
                    ],
                ]),
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                paginationRule: PaginationRule::all(),
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new PaginatedRequest();
        $request->setClient($client);

        $result = $client->send($request)->raw();

        expect($result)->toBeInstanceOf(PaginatedResult::class);
        expect($result->items())->toBe([1, 2]);
        expect($transport->getRecorded())->toHaveCount(2);
    });
});
