<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/json')]
final class JsonPayloadRequest extends AbstractRequest
{
    public function __construct(#[BodyRoot] public mixed $payload, #[Query] public string $query = 'fixture-query') {}
}
