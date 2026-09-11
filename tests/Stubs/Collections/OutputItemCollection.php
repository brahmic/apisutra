<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Collections;

use Brahmic\ApiSutra\Collections\AbstractTypedCollection;
use Brahmic\ApiSutra\Tests\Stubs\Dto\OutputItemDto;

/**
 * @extends AbstractTypedCollection<OutputItemDto>
 */
final readonly class OutputItemCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return OutputItemDto::class;
    }
}
