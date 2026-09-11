<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class EmptyStringCastPriorityDto extends AbstractDto
{
    public function __construct(
        #[From('name')]
        #[EmptyStringAsNull]
        #[Cast(UppercaseCast::class)]
        public string $name,
    ) {}
}
