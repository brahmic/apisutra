<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class StateProvider implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, ?PipelineContext $context): mixed
    {
        return $state->value;
    }
}
