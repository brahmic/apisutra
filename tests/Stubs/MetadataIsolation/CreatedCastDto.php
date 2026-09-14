<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;

final readonly class CreatedCastDto
{
    public function __construct(
        #[Cast(CreatedCast::class, ['nested' => [new CreatedValue()]])]
        public ?int $number = null,
    ) {
    }
}
