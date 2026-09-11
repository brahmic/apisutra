<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Behavior\Idempotent;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/idempotent')]
#[Idempotent(header: 'X-Idempotency')]
final class IdempotentRequest extends AbstractRequest
{
}
