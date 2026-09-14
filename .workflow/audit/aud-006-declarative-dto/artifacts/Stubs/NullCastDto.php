<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;

final readonly class NullCastDto
{
    public function __construct(#[Cast(ThrowOnNullCast::class)] public ?int $count = null)
    {
    }
}
