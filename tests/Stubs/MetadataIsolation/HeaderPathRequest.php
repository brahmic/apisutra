<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/items/{id}')]
final class HeaderPathRequest extends AbstractRequest
{
    #[Header('X-Number')]
    #[Cast(CountingCast::class, new MutableCounter())]
    public int $header = 0;

    #[Path('id')]
    #[Cast(CountingCast::class, new MutableCounter())]
    public int $id = 0;
}
