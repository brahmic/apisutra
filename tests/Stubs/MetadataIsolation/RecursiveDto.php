<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class RecursiveDto extends AbstractDto
{
    public function __construct(
        public ?RecursiveDto $child = null,
        public CreatedValue $state = new CreatedValue(),
    ) {
    }
}
