<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\AttributeRegistry;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Extensions\ExtensionRegistry;
use Brahmic\ApiSutra\Hooks\HookRegistry;
use Brahmic\ApiSutra\Tests\Stubs\Extensions\ExactResponseExtension;
use Brahmic\ApiSutra\Tests\Stubs\Extensions\ExactResponseHandler;
use Brahmic\ApiSutra\Tests\Stubs\Extensions\WildcardResponseExtension;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('ExtensionRegistry priority', function () {
    it('выбирает обработчик с более точным mime', function () {
        $registry = new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry());
        $registry->register(new WildcardResponseExtension());
        $registry->register(new ExactResponseExtension());

        $response = new ProviderResponse(
            status: 200,
            headers: ['Content-Type' => ['application/json; charset=utf-8']],
            body: '{}',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );
        $context = new PipelineContext(
            request: new SimpleGetRequest('q'),
            config: new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            traceId: 'trace',
        );

        $handler = $registry->resolveResponseHandler($response, $context);

        expect($handler)->toBeInstanceOf(ExactResponseHandler::class);
        expect($handler?->handle($response, $context))->toBe('exact');
    });
});
