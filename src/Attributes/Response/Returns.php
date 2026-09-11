<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes\Response;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Returns
{
    public function __construct(
        public string $response,
        public ?string $unwrap = null,
        public ?string $type = null,
    ) {}
}
