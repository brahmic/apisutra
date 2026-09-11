<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Collections\OutputItemCollection;

final readonly class CollectionMismatchDto extends AbstractDto
{
    public function __construct(
        #[Nested(type: JsonValue::class)]
        public OutputItemCollection $items,
    ) {}
}
