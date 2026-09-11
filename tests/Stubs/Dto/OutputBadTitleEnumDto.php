<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Enums\BadTitleStatus;

final readonly class OutputBadTitleEnumDto extends AbstractDto
{
    public function __construct(
        #[To('status')]
        public BadTitleStatus $status,
    ) {}
}
