<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Collections;

use Brahmic\ApiSutra\Collections\AbstractTypedCollection;
use Brahmic\ApiSutra\VO\Files\Base64File;

/**
 * @extends AbstractTypedCollection<Base64File>
 */
final readonly class Base64FileCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return Base64File::class;
    }
}
