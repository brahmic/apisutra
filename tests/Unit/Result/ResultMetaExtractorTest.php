<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Pagination\PaginationRule;
use Brahmic\ApiSutra\Result\PaginatedResult;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Support\Result\ProviderEnvelopeMeta;
use Brahmic\ApiSutra\Tests\Support\Result\ProviderEnvelopeMetaExtractor;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Metadata\PaginationMeta;

describe('ResultMetaExtractor', function () {
    it('fills meta for non-paginated success via extractor', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success([
                'id' => 10,
                'name' => 'Success',
                'resultCode' => 0,
                'resultMessage' => 'ok',
                'operationToken' => 'op-success',
            ]),
        ]);

        $extractor = new ProviderEnvelopeMetaExtractor();
        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                resultMetaExtractor: $extractor,
            ),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->isSuccess())->toBeTrue()
            ->and($result->debug)->toBeNull()
            ->and($result->data)->toBeInstanceOf(SimpleResponseDto::class)
            ->and(property_exists($result->data, 'resultCode'))->toBeFalse()
            ->and($result->meta)->toBeInstanceOf(ProviderEnvelopeMeta::class)
            ->and($result->meta?->resultCode)->toBe(0)
            ->and($result->meta?->resultMessage)->toBe('ok')
            ->and($result->meta?->operationToken)->toBe('op-success')
            ->and($result->response?->json('resultCode'))->toBe(0)
            ->and($extractor->calls)->toBe(1);
    });

    it('fills meta for non-paginated failed via extractor', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::make([
                'message' => 'Provider error',
                'resultCode' => -29,
                'resultMessage' => 'pending',
                'operationToken' => 'op-failed',
            ], 500),
        ]);

        $extractor = new ProviderEnvelopeMetaExtractor();
        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                resultMetaExtractor: $extractor,
            ),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->isFailed())->toBeTrue()
            ->and($result->meta)->toBeInstanceOf(ProviderEnvelopeMeta::class)
            ->and($result->meta?->resultCode)->toBe(-29)
            ->and($result->meta?->resultMessage)->toBe('pending')
            ->and($result->meta?->operationToken)->toBe('op-failed')
            ->and($result->response?->json('resultCode'))->toBe(-29)
            ->and($extractor->calls)->toBe(1);
    });

    it('does not override existing pagination meta', function () {
        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::success([
                'data' => [1],
                'meta' => [
                    'page' => 1,
                    'per_page' => 1,
                    'total' => 1,
                    'has_more' => false,
                ],
                'resultCode' => 0,
            ]),
        ]);

        $extractor = new ProviderEnvelopeMetaExtractor();
        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                paginationRule: PaginationRule::all(),
                resultMetaExtractor: $extractor,
            ),
            $transport,
        );

        $request = new PaginatedRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result)->toBeInstanceOf(PaginatedResult::class)
            ->and($result->meta)->toBeInstanceOf(PaginationMeta::class)
            ->and($extractor->calls)->toBe(0);
    });

    it('applies extractor in async send path', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success([
                'id' => 11,
                'name' => 'Async',
                'resultCode' => 0,
                'resultMessage' => 'ok-async',
                'operationToken' => 'op-async',
            ]),
        ]);

        $extractor = new ProviderEnvelopeMetaExtractor();
        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                resultMetaExtractor: $extractor,
            ),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $result = $request->sendAsync()->rawAsync()->wait();

        expect($result->isSuccess())->toBeTrue()
            ->and($result->debug)->toBeNull()
            ->and($result->meta)->toBeInstanceOf(ProviderEnvelopeMeta::class)
            ->and($result->meta?->resultMessage)->toBe('ok-async')
            ->and($result->response?->json('resultCode'))->toBe(0)
            ->and($extractor->calls)->toBe(1);
    });

    it('keeps behavior unchanged when extractor is null', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success([
                'id' => 12,
                'name' => 'No extractor',
                'resultCode' => 0,
                'resultMessage' => 'ok',
            ]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                resultMetaExtractor: null,
            ),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->isSuccess())->toBeTrue()
            ->and($result->meta)->toBeNull();
    });
});
