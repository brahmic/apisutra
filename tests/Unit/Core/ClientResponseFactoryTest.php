<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Response\ClientResponseFactory;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Result\ResolvedResult;
use Brahmic\ApiSutra\Tests\Stubs\Core\TestClientErrorMapper;
use Brahmic\ApiSutra\Tests\Stubs\Core\TestClientResponseFactory;
use Brahmic\ApiSutra\Tests\Stubs\Core\TestMapperAwareResponseFactory;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Errors\ClientErrorFactory;
use Brahmic\ApiSutra\VO\Errors\DefaultClientErrorMapper;
use Brahmic\ApiSutra\VO\Errors\RequestError;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;

describe('ClientResponseFactory', function () {
    it('формирует успешный клиентский ответ', function () {
        $execution = new ExecutionResult(
            data: ['id' => 1],
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
        );

        $resolved = new ResolvedResult($execution, new ClientErrorFactory(new DefaultClientErrorMapper()));
        $factory = new ClientResponseFactory();
        $response = $factory->make($resolved);

        expect($response->status)->toBe(200)
            ->and($response->body)->toBe(['id' => 1]);
    });

    it('формирует частичный ответ с mapped errors', function () {
        $prepared = new PreparedRequest(HttpMethod::GET, 'https://api.test');
        $providerResponse = new ProviderResponse(
            status: 500,
            headers: [],
            body: '{}',
            request: $prepared,
            duration: 0.01,
        );
        $error = new RequestError(
            code: ErrorCode::ServerError,
            message: 'Ошибка',
            response: $providerResponse,
            requestClass: 'TestRequest',
        );

        $execution = new ExecutionResult(
            data: ['items' => [1]],
            status: ResultStatus::PARTIAL,
            errors: new ErrorCollection([$error]),
        );

        $resolved = new ResolvedResult($execution, new ClientErrorFactory(new DefaultClientErrorMapper()));
        $factory = new ClientResponseFactory();
        $response = $factory->make($resolved);

        expect($response->status)->toBe(207)
            ->and($response->body['data'] ?? null)->toBe(['items' => [1]]);
        $errors = $response->body['errors'] ?? [];
        expect($errors)->toHaveCount(1)
            ->and($errors[0]['sdk_code'] ?? null)->toBe('server_error')
            ->and($errors[0]['provider_code'] ?? null)->toBe('500');
    });

    it('мапит http статус по sdk-коду ошибки', function () {
        $error = new RequestError(
            code: ErrorCode::Unauthorized,
            message: 'Нет доступа',
        );
        $execution = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([$error]),
        );

        $resolved = new ResolvedResult($execution, new ClientErrorFactory(new DefaultClientErrorMapper()));
        $factory = new ClientResponseFactory();
        $response = $factory->make($resolved);

        expect($response->status)->toBe(401);
        $errors = $response->body['errors'] ?? [];
        expect($errors[0]['sdk_code'] ?? null)->toBe('unauthorized');
    });

    it('клиент использует кастомную фабрику ответа', function () {
        $execution = new ExecutionResult(
            data: ['id' => 1],
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
        );
        $resolved = new ResolvedResult($execution, new ClientErrorFactory(new DefaultClientErrorMapper()));
        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                responseFactory: new TestClientResponseFactory(),
            ),
            new MockTransport(),
        );

        $response = $client->response($resolved);

        expect($response->status)->toBe(201)
            ->and($response->headers['X-Test'] ?? null)->toBe('ok')
            ->and($response->body)->toBe(['ok' => true]);
    });

    it('использует кастомный error mapper для default factory', function () {
        $error = new RequestError(
            code: ErrorCode::ValidationFailed,
            message: 'Ошибка',
            requestClass: 'TestRequest',
        );
        $execution = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([$error]),
        );

        $resolved = new ResolvedResult($execution, new ClientErrorFactory(new DefaultClientErrorMapper()));
        $factory = new ClientResponseFactory(new TestClientErrorMapper());
        $response = $factory->make($resolved);

        expect($response->status)->toBe(418);
        $errors = $response->body['errors'] ?? [];
        expect($errors[0]['client_code'] ?? null)->toBe('client.custom')
            ->and($errors[0]['app_code'] ?? null)->toBe('APP-001');
    });

    it('мапит nested ошибки в клиентском ответе', function () {
        $nested = new RequestError(
            code: ErrorCode::NotFound,
            message: 'Вложенная ошибка',
            requestClass: 'NestedRequest',
        );
        $error = new RequestError(
            code: ErrorCode::ServerError,
            message: 'Основная ошибка',
            nested: [$nested],
            requestClass: 'TestRequest',
        );

        $execution = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([$error]),
        );

        $resolved = new ResolvedResult($execution, new ClientErrorFactory(new DefaultClientErrorMapper()));
        $factory = new ClientResponseFactory();
        $response = $factory->make($resolved);

        $errors = $response->body['errors'] ?? [];
        expect($errors)->toHaveCount(1)
            ->and($errors[0]['nested'] ?? [])->toHaveCount(1)
            ->and($errors[0]['nested'][0]['message'] ?? null)->toBe('Вложенная ошибка')
            ->and($errors[0]['nested'][0]['sdk_code'] ?? null)->toBe('not_found');
    });

    it('клиент использует error mapper из конфига', function () {
        $error = new RequestError(
            code: ErrorCode::ValidationFailed,
            message: 'Ошибка',
            requestClass: 'TestRequest',
        );
        $execution = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([$error]),
        );
        $resolved = new ResolvedResult($execution, new ClientErrorFactory(new DefaultClientErrorMapper()));

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                errorMapper: new TestClientErrorMapper(),
            ),
            new MockTransport(),
        );

        $response = $client->response($resolved);

        expect($response->status)->toBe(418);
        $errors = $response->body['errors'] ?? [];
        expect($errors[0]['client_code'] ?? null)->toBe('client.custom');
    });

    it('передаёт error mapper в кастомную фабрику', function () {
        $error = new RequestError(
            code: ErrorCode::ValidationFailed,
            message: 'Ошибка',
            requestClass: 'TestRequest',
        );
        $execution = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([$error]),
        );
        $resolved = new ResolvedResult($execution, new ClientErrorFactory(new DefaultClientErrorMapper()));

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                errorMapper: new TestClientErrorMapper(),
                responseFactory: new TestMapperAwareResponseFactory(new DefaultClientErrorMapper()),
            ),
            new MockTransport(),
        );

        $response = $client->response($resolved);

        expect($response->status)->toBe(418);
        $errors = $response->body['errors'] ?? [];
        expect($errors[0]['client_code'] ?? null)->toBe('client.custom');
    });
});
