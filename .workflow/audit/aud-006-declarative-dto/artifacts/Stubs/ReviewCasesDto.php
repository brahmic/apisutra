<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;

final readonly class ReviewCasesDto extends StrictBase
{
    /** @param list<int> $ids
     * @param list<int> $strictIds
     * @param list<int> $parameterized
     * @param list<int>|null $guardedIds
     * @param list<int> $listCast
     */
    public function __construct(
        public array $ids = [],
        #[Nested(itemCast: ReviewIntegerItemCast::class)] public array $strictIds = [],
        #[Nested(itemCast: StrictScalarCast::class)] public array $parameterized = [],
        #[Cast(ReviewChildCast::class, PlainScalarDto::class)] public ?PlainScalarDto $child = null,
        #[Cast(ReviewChildCast::class, NullProviderDto::class)] public ?NullProviderDto $guardedChild = null,
        #[DefaultValue(provider: ReviewStateProvider::class, when: [ValueState::Present, ValueState::Null])]
        public ?string $keep = null,
        #[EmptyStringAsNull]
        #[DefaultValue(provider: ReviewStateProvider::class, when: [ValueState::Present, ValueState::Null])]
        public ?string $normalized = null,
        #[Cast(IdentityCast::class)]
        #[EmptyStringAsNull]
        #[DefaultValue(provider: ReviewStateProvider::class, when: [ValueState::Present, ValueState::Null])]
        public ?string $castSkip = null,
        #[DefaultValue(provider: ReviewListProvider::class, when: [ValueState::Null, ValueState::Present])]
        #[Nested(itemCast: ReviewIntegerItemCast::class)] public ?array $guardedIds = null,
        #[Cast(ReviewScalarListCast::class, 'int')] public array $listCast = [],
    ) {
    }
}
