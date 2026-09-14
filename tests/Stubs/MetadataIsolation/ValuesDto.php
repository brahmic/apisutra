<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Casts\IntegerCast;
use Brahmic\ApiSutra\Enums\Configuration\Environment;

final readonly class ValuesDto
{
    /** @param array<string, list<Environment>> $values */
    public function __construct(
        #[From('record_id')]
        #[Cast(IntegerCast::class)]
        public int $id = 7,
        public Environment $environment = Environment::Production,
        public array $values = ['env' => [Environment::Testing]],
    ) {
    }
}
