<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Continuation;

use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;

final readonly class ContinuationContext
{
    public function __construct(
        public ?string $finalType,
        public ?string $unwrap,
        public ?string $sourceRequestClass,
        public ContinuationMode $mode,
    ) {
    }
}
