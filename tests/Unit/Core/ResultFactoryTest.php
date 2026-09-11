<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\Pipeline\Result\ResultFactory;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('ResultFactory', function () {
    it('строит ошибку из ответа 404', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );
        $context->response = new ProviderResponse(
            status: 404,
            headers: ['Content-Type' => ['application/json']],
            body: json_encode(['message' => 'Not Found'], JSON_UNESCAPED_UNICODE) ?: '',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );

        $factory = new ResultFactory(new ErrorPolicy());
        $result = $factory->buildFailedResult($request, $context, []);

        expect($result->status)->toBe(ResultStatus::FAILED);
        expect($result->errors->first()?->code)->toBe(ErrorCode::NotFound);
        expect($result->errors->first()?->message)->toBe('Not Found');
    });

    it('использует ServerError при отсутствии ответа', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $factory = new ResultFactory(new ErrorPolicy());
        $result = $factory->buildFailedResult($request, $context, []);

        expect($result->status)->toBe(ResultStatus::FAILED);
        expect($result->errors->first()?->code)->toBe(ErrorCode::ServerError);
    });
});
