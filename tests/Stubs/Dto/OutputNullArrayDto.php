<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class OutputNullArrayDto extends AbstractDto
{
    /**
     * @param array<int, string|null> $items Массив значений, допускающий null.
     */
    public function __construct(
        #[To('items')]
        public array $items,
    ) {}
}
