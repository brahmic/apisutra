<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class PolymorphicOwnerOrganizationDto extends AbstractDto
{
    public function __construct(
        public string $title,
    ) {}
}
