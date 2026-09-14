<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/body')]
final class BodyCastRequest extends AbstractRequest
{
    #[Body]
    #[Cast(CreatedCast::class, ['nested' => [new CreatedValue()]])]
    public int $number = 0;
}
