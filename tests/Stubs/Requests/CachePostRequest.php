<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/items')]
final class CachePostRequest extends AbstractRequest
{
    public function __construct(#[Body] public string $value = 'payload') {}
}
