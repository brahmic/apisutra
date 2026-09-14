<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;

final readonly class GuardedTypedDto
{
    public function __construct(
        #[DefaultValue(provider: RejectStateDefault::class, when: [ValueState::Missing])]
        #[Nested(type: PlainScalarDto::class)]
        public TypedItems $items,
    ) {
    }
}
