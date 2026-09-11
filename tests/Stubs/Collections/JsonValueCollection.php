<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Collections;

use Brahmic\ApiSutra\Collections\AbstractTypedCollection;
use Brahmic\ApiSutra\Tests\Stubs\Dto\JsonValue;

/**
 * @extends AbstractTypedCollection<JsonValue>
 */
final readonly class JsonValueCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return JsonValue::class;
    }
}
