<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class UnsupportedPrivateDto extends AbstractDto
{
    public function __construct(
        private string $secret,
    ) {}
}
