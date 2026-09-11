<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class CastAttributeDto extends AbstractDto
{
    public function __construct(
        #[Cast(UppercaseCast::class)]
        public string $value,
    ) {}
}
