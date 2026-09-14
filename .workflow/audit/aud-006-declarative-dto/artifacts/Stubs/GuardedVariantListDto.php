<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;

final readonly class GuardedVariantListDto
{
    /** @param list<MappedDto|RawVariant> $items */
    public function __construct(
        #[DefaultValue(provider: ListShapeProvider::class, when: [ValueState::Present])]
        #[Nested(itemCast: UnknownVariantCast::class, discriminator: 'type', map: ['known' => MappedDto::class])]
        public array $items,
    ) {
    }
}
