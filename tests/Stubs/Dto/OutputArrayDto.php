<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class OutputArrayDto extends AbstractDto
{
    public function __construct(
        #[To('payload')]
        public ArrayValue $payload,
    ) {}
}
