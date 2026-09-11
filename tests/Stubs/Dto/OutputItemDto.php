<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class OutputItemDto extends AbstractDto
{
    public function __construct(
        #[To('id')]
        public int $id,
        #[To('label')]
        public string $label,
    ) {}
}
