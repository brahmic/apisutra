<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\AttributeRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Pipeline\PipelineStage;
use Brahmic\ApiSutra\Tests\Stubs\Attributes\ContextProbeAttribute;
use Brahmic\ApiSutra\Tests\Stubs\Attributes\ContextProbeHandler;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ContextProbeRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('AttributeContext', function () {
    it('передаёт classAttributes и PipelineContext в handler', function () {
        ContextProbeHandler::reset();
        $registry = new AttributeRegistry();
        $registry->register(ContextProbeAttribute::class, ContextProbeHandler::class);

        $request = new ContextProbeRequest();
        $context = new PipelineContext(
            request: $request,
            config: new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            traceId: 'trace',
        );

        $registry->processStage($request, $context, PipelineStage::BeforeSend, []);

        $propertyContext = null;
        foreach (ContextProbeHandler::$contexts as $item) {
            if ($item->attribute->value === 'property') {
                $propertyContext = $item;
                break;
            }
        }

        expect($propertyContext)->not->toBeNull()
            ->and($propertyContext?->classAttributes)->toHaveCount(3)
            ->and($propertyContext?->context)->toBe($context)
            ->and($propertyContext?->stage)->toBe(PipelineStage::BeforeSend);
    });
});
