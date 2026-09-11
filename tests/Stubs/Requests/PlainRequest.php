<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/plain')]
final class PlainRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $query,
    ) {}
}
