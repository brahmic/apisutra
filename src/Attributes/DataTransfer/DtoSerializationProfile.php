<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes\DataTransfer;

use Attribute;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DtoSerializationProfile
{
    /**
     * @param class-string<DtoSerializationProfileInterface> $class
     */
    public function __construct(
        public string $class,
    ) {
    }
}
