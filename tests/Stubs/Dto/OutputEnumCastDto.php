<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Casts\EnumToStringCast;
use Brahmic\ApiSutra\Tests\Stubs\Enums\TitleStatus;

final readonly class OutputEnumCastDto extends AbstractDto
{
    public function __construct(
        #[To('status')]
        #[Cast(EnumToStringCast::class)]
        public TitleStatus $status,
    ) {}
}
