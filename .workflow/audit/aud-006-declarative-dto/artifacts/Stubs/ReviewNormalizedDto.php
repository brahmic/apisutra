<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrate;
use Brahmic\ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;

#[DtoHydrate(emptyStringBehavior: EmptyStringBehavior::NullIfEmpty)]
final readonly class ReviewNormalizedDto
{
    public function __construct(
        #[DefaultValue(provider: ReviewStateProvider::class, when: [ValueState::Present, ValueState::Null])]
        public ?string $value = null,
    ) {
    }
}
