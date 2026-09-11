<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/items/{id}')]
final class UriPathRequest extends AbstractRequest
{
    public function __construct(#[Path] public mixed $id = null) {}
}
