<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Http;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class DestinationGuard
{
    public static function checkCapability(object $sender, ?RequestDestination $destination): void
    {
        if (!$destination?->requiresIsolation()) {
            return;
        }
        if (!$sender instanceof DestinationAwareInterface) {
            throw new ConfigurationException($sender::class . ' не поддерживает изоляцию назначения запроса');
        }
        $sender->assertSupportsDestination($destination);
    }

    public static function checkContext(PipelineContext $context): void
    {
        if ($context->destination !== null && $context->preparedRequest !== null) {
            $context->destination->assertUrl($context->preparedRequest->url);
            $context->preparedRequest = $context->preparedRequest->with(destination: $context->destination);
        }
    }

    public static function checkRequest(PreparedRequest $request): void
    {
        $request->destination?->assertUrl($request->url);
    }
}
