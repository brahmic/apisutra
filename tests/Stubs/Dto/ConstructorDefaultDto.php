<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class ConstructorDefaultDto extends AbstractDto
{
    public function __construct(
        #[From('middle_name')]
        public ?string $middleName = 'somename',
    ) {}
}
