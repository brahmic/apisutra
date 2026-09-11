<?php

declare(strict_types=1);

namespace Acme\Fallback\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/fallback')]
final class FallbackRequest extends AbstractRequest
{
}
