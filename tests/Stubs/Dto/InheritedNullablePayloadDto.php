<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;

final readonly class InheritedNullablePayloadDto extends InheritedBaseStatusDto
{
    #[From('result')]
    public ?NestedItemDto $result;
}
