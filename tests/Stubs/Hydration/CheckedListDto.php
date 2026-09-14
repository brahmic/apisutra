<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;

final readonly class CheckedListDto
{
    /** @param list<AddressDto|array<string, mixed>>|null $items */
    public function __construct(
        #[DefaultValue(provider: ListProvider::class, when: [ValueState::Null, ValueState::Present])]
        #[Nested(discriminator: 'kind', map: ['known' => AddressDto::class])]
        public ?array $items = null,
    ) {
    }
}
