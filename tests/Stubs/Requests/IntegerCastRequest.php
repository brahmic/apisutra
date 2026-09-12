<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Casts\IntegerCast;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/integer')]
final class IntegerCastRequest extends AbstractRequest
{
    public function __construct(#[Body, Cast(IntegerCast::class)] public mixed $id) {}
}
