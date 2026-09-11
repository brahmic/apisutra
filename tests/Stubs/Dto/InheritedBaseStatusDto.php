<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Enums\TestStatus;

abstract readonly class InheritedBaseStatusDto extends AbstractDto
{
    public function __construct(
        #[From('status')]
        public TestStatus $status,
    ) {}
}
