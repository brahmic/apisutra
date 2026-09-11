<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Exceptions\Request\ClientException;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Exceptions\Request\BadGatewayException;
use Brahmic\ApiSutra\Exceptions\Request\ForbiddenException;
use Brahmic\ApiSutra\Exceptions\Request\GatewayTimeoutException;
use Brahmic\ApiSutra\Exceptions\Request\InternalServerException;
use Brahmic\ApiSutra\Exceptions\Request\NotFoundException;
use Brahmic\ApiSutra\Exceptions\Request\PaymentRequiredException;
use Brahmic\ApiSutra\Exceptions\Request\RateLimitException;
use Brahmic\ApiSutra\Exceptions\Request\RequestTimeoutException;
use Brahmic\ApiSutra\Exceptions\Request\ServiceUnavailableException;
use Brahmic\ApiSutra\Exceptions\Request\UnauthorizedException;
use Brahmic\ApiSutra\Exceptions\Request\UnprocessableEntityException;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;

describe('ErrorPolicy', function () {
    it('определяет отказ при отсутствии ответа', function () {
        $policy = new ErrorPolicy();
        $request = new SimpleGetRequest('q');

        expect($policy->hasRequestFailed($request, null))->toBeTrue();
    });

    it('не считает успешный ответ ошибкой', function () {
        $policy = new ErrorPolicy();
        $request = new SimpleGetRequest('q');
        $response = new ProviderResponse(
            status: 200,
            headers: [],
            body: '{}',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );

        expect($policy->hasRequestFailed($request, $response))->toBeFalse();
    });

    it('маппит 404 в NotFoundException', function () {
        $policy = new ErrorPolicy();
        $request = new SimpleGetRequest('q');
        $response = new ProviderResponse(
            status: 404,
            headers: ['Content-Type' => ['application/json']],
            body: json_encode(['message' => 'Not Found'], JSON_UNESCAPED_UNICODE) ?: '',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );

        $exception = $policy->getRequestExceptionInternal($request, $response);

        expect($exception)->toBeInstanceOf(NotFoundException::class);
        expect($exception?->getMessage())->toBe('Not Found');
    });

    it('маппит 429 и извлекает Retry-After', function () {
        $policy = new ErrorPolicy();
        $request = new SimpleGetRequest('q');
        $response = new ProviderResponse(
            status: 429,
            headers: ['Retry-After' => ['120']],
            body: json_encode(['message' => 'Too Many'], JSON_UNESCAPED_UNICODE) ?: '',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );

        $exception = $policy->getRequestExceptionInternal($request, $response);

        expect($exception)->toBeInstanceOf(RateLimitException::class);
        expect($exception?->retryAfter)->toBe(120);
    });

    it('маппит статусы в исключения', function (int $status, string $exceptionClass) {
        $policy = new ErrorPolicy();
        $request = new SimpleGetRequest('q');
        $response = new ProviderResponse(
            status: $status,
            headers: ['Content-Type' => ['application/json']],
            body: json_encode(['message' => 'Ошибка'], JSON_UNESCAPED_UNICODE) ?: '',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );

        $exception = $policy->getRequestExceptionInternal($request, $response);

        expect($exception)->toBeInstanceOf($exceptionClass);
        expect($exception?->getMessage())->toBe('Ошибка');
    })->with([
        [401, UnauthorizedException::class],
        [402, PaymentRequiredException::class],
        [403, ForbiddenException::class],
        [404, NotFoundException::class],
        [408, RequestTimeoutException::class],
        [422, UnprocessableEntityException::class],
        [429, RateLimitException::class],
        [500, InternalServerException::class],
        [502, BadGatewayException::class],
        [503, ServiceUnavailableException::class],
        [504, GatewayTimeoutException::class],
    ]);

    it('сохраняет немаппируемый 4xx в общем ClientException', function () {
        $policy = new ErrorPolicy();
        $request = new SimpleGetRequest('q');
        $response = new ProviderResponse(
            status: 418,
            headers: ['Content-Type' => ['application/json']],
            body: json_encode(['message' => 'Teapot'], JSON_UNESCAPED_UNICODE) ?: '',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );

        $exception = $policy->getRequestExceptionInternal($request, $response);

        expect($exception)->toBeInstanceOf(ClientException::class)
            ->and($exception->response)->toBe($response);
    });

    it('использует fallback сообщение при пустом body', function () {
        $policy = new ErrorPolicy();
        $request = new SimpleGetRequest('q');
        $response = new ProviderResponse(
            status: 500,
            headers: [],
            body: '',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );

        $exception = $policy->getRequestExceptionInternal($request, $response);

        expect($exception)->toBeInstanceOf(InternalServerException::class);
        expect($exception?->getMessage())->toBe('HTTP 500');
    });

    it('использует fallback сообщение при не-JSON body', function () {
        $policy = new ErrorPolicy();
        $request = new SimpleGetRequest('q');
        $response = new ProviderResponse(
            status: 500,
            headers: ['Content-Type' => ['text/html']],
            body: '<html>error</html>',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );

        $exception = $policy->getRequestExceptionInternal($request, $response);

        expect($exception)->toBeInstanceOf(InternalServerException::class);
        expect($exception?->getMessage())->toBe('HTTP 500');
    });
});
