<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\Map;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class IncomingAttributesDto
{
    public function __construct(
        #[From('source')] public mixed $from = null,
        #[Map('source')] public mixed $map = null,
        #[Nested(type: RecordDto::class)] public mixed $nested = null,
        #[Cast(CountingCast::class)] public mixed $cast = null,
        #[DateTimeFrom] public mixed $date = null,
        #[EmptyStringAsNull] public mixed $empty = null,
        #[DefaultValue(value: 1)] public mixed $default = null,
    ) {
    }
}
