<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/wire')]
final class BodyCastRequest extends AbstractRequest
{
    public function __construct(#[Body] #[Cast(OpaqueCast::class)] public mixed $payload)
    {
    }
}
