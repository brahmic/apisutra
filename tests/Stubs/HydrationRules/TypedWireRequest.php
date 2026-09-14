<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/wire')]
final class TypedWireRequest extends AbstractRequest
{
    public function __construct(#[BodyRoot] public WirePlainDto $payload)
    {
    }
}
