<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Post('/wire')]
final class Wire extends AbstractRequest
{
    public function __construct(
        #[Query('preview')] public bool $preview,
        #[BodyRoot] public mixed $payload,
    ) {}
}
