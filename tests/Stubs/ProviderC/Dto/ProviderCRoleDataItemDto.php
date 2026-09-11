<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderC\Dto;

use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class ProviderCRoleDataItemDto extends AbstractDto
{
    public function __construct(
        public string $id,
    ) {}
}
