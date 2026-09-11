<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;

final readonly class InheritedEmptyStringNullableDto extends InheritedBaseStatusDto
{
    #[From('name')]
    #[EmptyStringAsNull]
    public ?string $name;
}
