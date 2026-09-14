<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;

final readonly class NormalizerDto
{
    public function __construct(
        #[EmptyStringAsNull] public ?string $empty = null,
        #[Cast(ReturnCast::class, 'cast')] public ?string $cast = null,
        public ?string $plain = null,
    ) {
    }
}
