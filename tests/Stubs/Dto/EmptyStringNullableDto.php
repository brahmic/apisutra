<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class EmptyStringNullableDto extends AbstractDto
{
    public function __construct(
        #[From('name')]
        #[EmptyStringAsNull]
        public ?string $name = null,
    ) {}
}
