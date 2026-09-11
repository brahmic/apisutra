<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Request\RequestExecution;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\Result\ResultHandle;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ThrowingCompositeRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Request async', function () {
    it('AbstractRequest::sendAsync возвращает Promise с результатом', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'A']),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $handle = $request->sendAsync();

        expect($handle)->toBeInstanceOf(ResultHandle::class);

        $result = $handle->rawAsync()->wait();
        expect($result->data)->toBeInstanceOf(SimpleResponseDto::class);
        expect($result->data->id)->toBe(1);
    });

    it('RequestExecution::sendAsync возвращает Promise с результатом', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 2, 'name' => 'B']),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $execution = new RequestExecution(
            request: $request,
            options: RequestOptions::empty(),
        );

        $result = $execution->sendAsync()->rawAsync()->wait();

        expect($result->data)->toBeInstanceOf(SimpleResponseDto::class);
        expect($result->data->id)->toBe(2);
    });

    it('возвращает failed результат при ошибке', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::serverError(),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $result = $request->sendAsync()->rawAsync()->wait();

        expect($result->isFailed())->toBeTrue();
    });

    it('rejection при throwOnErrors', function () {
        $transport = new MockTransport();
        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                throwOnErrors: true,
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new ThrowingCompositeRequest();
        $request->setClient($client);

        expect(fn () => $request->sendAsync()->rawAsync()->wait())
            ->toThrow(RuntimeException::class);
    });
});
