<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Casts\IntegerCast;

final readonly class ExplicitCastDto extends StrictBase
{
    public function __construct(
        #[Cast(IntegerCast::class)] public ?int $count = null,
        #[Cast(IdentityCast::class)] public ?int $unchecked = null,
    ) {
    }
}
