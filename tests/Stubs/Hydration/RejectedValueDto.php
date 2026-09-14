<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;

final readonly class RejectedValueDto
{
    public function __construct(
        #[DefaultValue(
            provider: RejectingProvider::class,
            when: [ValueState::Missing, ValueState::Null, ValueState::Present],
        )]
        public ?int $count = null,
    ) {
    }
}
