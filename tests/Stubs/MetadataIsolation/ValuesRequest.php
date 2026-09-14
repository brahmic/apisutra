<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/values')]
final class ValuesRequest extends AbstractRequest
{
    public function __construct(#[Query] public int $number = 0)
    {
    }
}
