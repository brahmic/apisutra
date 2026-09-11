<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class NestedItemDto extends AbstractDto
{
    public function __construct(
        #[From('id')]
        public int $id,
        #[From('name')]
        public string $name,
    ) {}
}
