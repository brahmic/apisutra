<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;

final readonly class TokenResponseDto extends AbstractResponseDto
{
    public function __construct(
        public string $token,
    ) {}
}
