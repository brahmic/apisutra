<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;

final readonly class NullProviderDto
{
    public function __construct(
        #[DefaultValue(provider: RejectStateDefault::class, when: [ValueState::Null])]
        public ?int $count = null,
    ) {
    }
}
