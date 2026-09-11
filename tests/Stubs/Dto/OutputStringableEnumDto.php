<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Enums\StringableTitleStatus;

final readonly class OutputStringableEnumDto extends AbstractDto
{
    public function __construct(
        #[To('status')]
        public StringableTitleStatus $status,
    ) {}
}
