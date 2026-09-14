<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/wire')]
final class BodyRequest extends AbstractRequest
{
    public function __construct(#[Body] public mixed $payload)
    {
    }
}
