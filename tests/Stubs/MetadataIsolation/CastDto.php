<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class CastDto extends AbstractDto
{
    public function __construct(
        #[Cast(CountingCast::class, new MutableCounter())]
        public int $number = 0,
    ) {
    }
}
