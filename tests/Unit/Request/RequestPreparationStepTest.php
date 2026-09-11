<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Flow\RequestPreparationStep;
use Brahmic\ApiSutra\Pipeline\Preparation\PreparedRequestFactory;
use Brahmic\ApiSutra\Pipeline\Preparation\RequestPreparer;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\Serialization\Serializer;
use Brahmic\ApiSutra\Tests\Stubs\Dto\OutputAddressDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\OutputItemDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\OutputUserDto;
use Brahmic\ApiSutra\Tests\Stubs\Requests\DtoBodyRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\IdempotentRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('RequestPreparationStep', function () {
    it('готовит запрос и применяет overrides', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $preparer = new RequestPreparer($config);
        $step = new RequestPreparationStep(
            new PreparedRequestFactory(new Serializer(new CastRegistry()), $preparer),
            new AuditLogger($config),
        );

        $request = new IdempotentRequest();
        $options = RequestOptions::empty()
            ->withHeader('X-Extra', '2')
            ->withIdempotencyKey('idem-key');

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
            options: $options,
        );

        $prepared = $step->prepare($request, $context);

        expect($prepared->headers['X-Extra'] ?? null)->toBe('2');
        expect($prepared->headers['X-Idempotency'] ?? null)->toBe('idem-key');
        expect($context->preparedRequest)->toBe($prepared);
    });

    it('готовит запрос с DTO body через DtoSerializer', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            environment: Environment::Testing,
        );
        $preparer = new RequestPreparer($config);
        $step = new RequestPreparationStep(
            new PreparedRequestFactory(new Serializer(new CastRegistry()), $preparer),
            new AuditLogger($config),
        );

        $request = new DtoBodyRequest(new OutputUserDto(
            userId: 20,
            address: new OutputAddressDto('Omsk', '644000'),
            items: [new OutputItemDto(5, 'Fifth')],
            title: 'hello',
            tags: ['x'],
            plainValue: null,
        ));

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $step->prepare($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($body['payload']['user_id'] ?? null)->toBe(20);
        expect($body['payload']['profile']['city'] ?? null)->toBe('Omsk');
        expect($body['payload']['items'][0]['label'] ?? null)->toBe('Fifth');
    });
});
