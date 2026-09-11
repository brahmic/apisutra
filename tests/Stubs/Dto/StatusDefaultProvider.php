<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class StatusDefaultProvider implements DefaultValueProviderInterface
{
    #[\Override]
    public function resolve(
        mixed $value,
        ValueState $state,
        array $source,
        ?PipelineContext $context,
    ): mixed {
        $marker = $source['marker'] ?? 'none';
        return 'provider:' . $state->value . ':' . $marker;
    }
}
