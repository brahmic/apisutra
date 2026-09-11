<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/body-root/scalar')]
final class BodyRootGetScalarRequest extends AbstractRequest
{
    public function __construct(
        #[Query('q')]
        public string $query,
        #[BodyRoot]
        public string $payload,
    ) {}
}
