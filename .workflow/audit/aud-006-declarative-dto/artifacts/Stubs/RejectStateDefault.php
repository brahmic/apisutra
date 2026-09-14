<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class RejectStateDefault implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, ?PipelineContext $context): mixed
    {
        throw HydrationException::invalidValue(
            $state === ValueState::Null ? 'explicit_null_not_allowed' : 'required_field_missing',
            'present non-null value',
            $state->value,
        );
    }
}
