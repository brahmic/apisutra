<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes\DataTransfer;

use Attribute;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DtoHydrationProfile
{
    /**
     * @param class-string<DtoHydrationProfileInterface> $class
     */
    public function __construct(
        public string $class,
    ) {
    }
}
