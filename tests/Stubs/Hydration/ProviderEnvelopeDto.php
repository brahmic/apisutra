<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class ProviderEnvelopeDto
{
    /** @param list<RejectedValueDto> $items */
    public function __construct(
        #[Nested] public ?RejectedValueDto $child = null,
        #[Nested(type: RejectedValueDto::class)] public array $items = [],
    ) {
    }
}
