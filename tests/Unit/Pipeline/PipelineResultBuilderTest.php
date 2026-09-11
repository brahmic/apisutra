<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\AttributeRegistry;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Request\RequestException;
use Brahmic\ApiSutra\Exceptions\Validation\ValidationException;
use Brahmic\ApiSutra\Extensions\ExtensionRegistry;
use Brahmic\ApiSutra\Hooks\HookRegistry;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Flow\ExecutionResultBuilder;
use Brahmic\ApiSutra\Pipeline\Hydration\ResponseHydrator;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\VO\Errors\ValidationError;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('ExecutionResultBuilder', function () {
    it('формирует результат при ошибке валидации', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $builder = new ExecutionResultBuilder(
            $config,
            new AuditLogger($config),
            makeResponseHydrator($config),
        );

        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );
        $errors = [new ValidationError('field', 'required', 'Поле обязательно')];

        $audit = [];
        $result = $builder->buildValidationFailure($request, $context, $audit, microtime(true), $errors);

        expect($result->status)->toBe(ResultStatus::FAILED);
        expect($result->errors->first()?->code)->toBe(ErrorCode::ValidationFailed);
        expect($result->validationErrors)->toHaveCount(1);
        expect($result->exception)->toBeInstanceOf(ValidationException::class);
    });

    it('создаёт успешный результат с debug данными', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', debug: true, environment: Environment::Testing);
        $builder = new ExecutionResultBuilder(
            $config,
            new AuditLogger($config),
            makeResponseHydrator($config),
        );

        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );
        $context->response = new ProviderResponse(
            status: 200,
            headers: [],
            body: '{}',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );

        $prepared = new PreparedRequest(HttpMethod::GET, 'https://api.test');
        $audit = [];
        $result = $builder->buildSuccessResult($request, $context, $audit, microtime(true), $prepared, ['ok' => true]);

        expect($result->status)->toBe(ResultStatus::SUCCESS);
        expect($result->debug)->not->toBeNull();
        expect($result->debug?->preparedRequest)->toBe($prepared);
        expect($result->debug?->response)->toBe($context->response);
    });

    it('маппит ConfigurationException в ConfigurationError', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $builder = new ExecutionResultBuilder(
            $config,
            new AuditLogger($config),
            makeResponseHydrator($config),
        );

        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $audit = [];
        $result = $builder->buildExceptionResult(
            $request,
            $context,
            $audit,
            microtime(true),
            new ConfigurationException('Bad config'),
        );

        expect($result->status)->toBe(ResultStatus::FAILED);
        expect($result->errors->first()?->code)->toBe(ErrorCode::ConfigurationError);
    });

    it('сохраняет context response в failed result для non-RequestException', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $builder = new ExecutionResultBuilder(
            $config,
            new AuditLogger($config),
            makeResponseHydrator($config),
        );

        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );
        $context->response = new ProviderResponse(
            status: 503,
            headers: [],
            body: '{"message":"upstream fail"}',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );

        $audit = [];
        $result = $builder->buildExceptionResult(
            $request,
            $context,
            $audit,
            microtime(true),
            new ConfigurationException('Bad config'),
        );

        expect($result->status)->toBe(ResultStatus::FAILED)
            ->and($result->errors->first()?->code)->toBe(ErrorCode::ConfigurationError)
            ->and($result->response)->toBe($context->response)
            ->and($result->errors->first()?->response)->toBe($context->response);
    });

    it('маппит статусы HTTP в ErrorCode', function (int $status, ErrorCode $expected) {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $builder = new ExecutionResultBuilder(
            $config,
            new AuditLogger($config),
            makeResponseHydrator($config),
        );

        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $response = new ProviderResponse(
            status: $status,
            headers: [],
            body: '{}',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );

        $exception = new RequestException('fail', $response);
        $audit = [];
        $result = $builder->buildExceptionResult($request, $context, $audit, microtime(true), $exception);

        expect($result->errors->first()?->code)->toBe($expected);
    })->with([
        [401, ErrorCode::Unauthorized],
        [403, ErrorCode::Forbidden],
        [404, ErrorCode::NotFound],
        [422, ErrorCode::ValidationFailed],
        [429, ErrorCode::RateLimited],
        [500, ErrorCode::ServerError],
        [502, ErrorCode::BadGateway],
        [503, ErrorCode::ServiceUnavailable],
        [504, ErrorCode::GatewayTimeout],
        [418, ErrorCode::ClientError],
    ]);
});

function makeResponseHydrator(ClientConfig $config): ResponseHydrator
{
    return new ResponseHydrator(
        $config,
        new Hydrator(new CastRegistry()),
        new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry()),
    );
}
