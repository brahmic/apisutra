<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Preparation;

use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\VO\Http\TransportOptions;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class TimeoutResolver
{
    public static function resolve(PipelineContext $context): TransportOptions
    {
        $request = $context->request;
        $attribute = $request instanceof AbstractRequest ? $request->getTimeoutAttribute() : null;
        $options = $context->options ?? ($request instanceof AbstractRequest ? $request->getOptions() : null);
        return new TransportOptions(
            self::milliseconds($options?->getTimeoutOverride() ?? $attribute->seconds ?? $context->config->timeout),
            self::milliseconds($options?->getConnectTimeoutOverride() ?? $attribute->connectTimeout ?? $context->config->connectTimeout),
            $context->budget,
            $context->destination,
            $context->fileTransfer,
        );
    }

    private static function milliseconds(int $seconds): int
    {
        if ($seconds < 0 || $seconds > intdiv(PHP_INT_MAX, 1000)) {
            throw new ConfigurationException('Недопустимый timeout/connectTimeout в секундах');
        }
        return $seconds * 1000;
    }
}
