<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Collections\AbstractTypedCollection;

/** @extends AbstractTypedCollection<PlainScalarDto> */
final readonly class TypedItems extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return PlainScalarDto::class;
    }
}
