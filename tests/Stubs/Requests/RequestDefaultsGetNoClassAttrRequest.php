<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/defaults')]
final class RequestDefaultsGetNoClassAttrRequest extends AbstractRequest
{
    public function __construct(
        public string $plain,
    ) {}
}
