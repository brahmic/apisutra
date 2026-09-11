<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\AttributeRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Enums\Pipeline\PipelineStage;
use Brahmic\ApiSutra\Pipeline\Attributes\StageProcessor;
use Brahmic\ApiSutra\Pipeline\Diagnostics\AuditLogger;
use Brahmic\ApiSutra\Pipeline\Flow\PipelineContextFactory;
use Brahmic\ApiSutra\Pipeline\Preparation\RequestPreparer;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\TraceOverrideRequest;

describe('PipelineContextFactory', function () {
    it('применяет role override из options и связывает контекст с запросом', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $factory = new PipelineContextFactory(
            $config,
            new RequestPreparer($config),
            new StageProcessor(new AttributeRegistry()),
            new AuditLogger($config),
        );

        $options = RequestOptions::empty()->withRole(RequestRole::Nested)->withTraceId('trace-opt');
        $request = new SimpleGetRequest('q');

        $context = $factory->create($request, RequestRole::Root, null, null, null, $options);

        expect($context->role)->toBe(RequestRole::Nested);
        expect($context->traceId)->toBe('trace-opt');
        expect($request->getContext())->toBe($context);
    });

    it('использует override из запроса, если options не заданы', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $factory = new PipelineContextFactory(
            $config,
            new RequestPreparer($config),
            new StageProcessor(new AttributeRegistry()),
            new AuditLogger($config),
        );

        $request = new TraceOverrideRequest('trace-request', RequestRole::Nested);

        $context = $factory->create($request, RequestRole::Root, null, null, null, null);

        expect($context->role)->toBe(RequestRole::Nested);
        expect($context->traceId)->toBe('trace-request');
    });

    it('добавляет audit событие на старте', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $factory = new PipelineContextFactory(
            $config,
            new RequestPreparer($config),
            new StageProcessor(new AttributeRegistry()),
            new AuditLogger($config),
        );

        $request = new SimpleGetRequest('q');
        $context = $factory->create($request, RequestRole::Root, null, null, null, null);
        $audit = [];

        $start = $factory->start($request, $context, $audit);

        expect($start)->toBeFloat();
        expect($audit)->toHaveCount(1);
        expect($audit[0]->stage)->toBe(PipelineStage::Started);
    });
});
