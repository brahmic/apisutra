<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\AttributeRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Pipeline\PipelineStage;
use Brahmic\ApiSutra\Tests\Stubs\Attributes\TagAttribute;
use Brahmic\ApiSutra\Tests\Stubs\Attributes\TagAttributeHandler;
use Brahmic\ApiSutra\Tests\Stubs\Requests\AttributeStageRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('AttributeRegistry', function () {
    it('обрабатывает атрибуты класса и свойства', function () {
        $registry = new AttributeRegistry();
        $registry->register(TagAttribute::class, TagAttributeHandler::class);

        $request = new AttributeStageRequest();
        $context = new PipelineContext(
            request: $request,
            config: new ClientConfig(
                baseUrl: 'https://provider.test',
                environment: Environment::Testing,
            ),
            traceId: 'trace',
        );

        $result = $registry->processStage($request, $context, PipelineStage::BeforeSend, []);

        expect($result)->toBe(['class', 'property']);
    });
});
