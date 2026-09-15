<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class DisplayNameProvider implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, ?PipelineContext $context): mixed
    {
        $sku = $source['vendor_code'] ?? null;
        if (!is_string($sku)) {
            throw HydrationException::invalidValue('invalid_label_source', 'string vendor_code', get_debug_type($sku));
        }

        return 'Товар ' . $sku;
    }
}
