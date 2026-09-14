<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Collections\AbstractTypedCollection;

/** @extends AbstractTypedCollection<RecordDto> */
final readonly class RecordCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return RecordDto::class;
    }
}
