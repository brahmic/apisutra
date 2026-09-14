<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/wire')]
final class BodyRootRequest extends AbstractRequest
{
    public function __construct(#[BodyRoot] public mixed $payload)
    {
    }
}
