<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;
use RuntimeException;

#[Get('/throw-endpoint')]
final class ThrowingEndpointRequest extends AbstractRequest
{
    protected function resolveEndpoint(): ?string
    {
        throw new RuntimeException('Ошибка подготовки endpoint');
    }
}
