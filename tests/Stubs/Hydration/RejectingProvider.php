<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use DomainException;

final readonly class RejectingProvider implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, ?PipelineContext $context): mixed
    {
        throw HydrationException::invalidValue(
            'provider_rejected_value',
            'accepted value',
            $state->value,
            ($source['detail'] ?? false) ? 'detail' : '',
            new DomainException('Причина отказа провайдера'),
        );
    }
}
