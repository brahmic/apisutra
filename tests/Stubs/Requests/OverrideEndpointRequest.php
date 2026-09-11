<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;

#[Get('/declared')]
#[Returns(SimpleResponseDto::class)]
final class OverrideEndpointRequest extends AbstractRequest
{
    protected function resolveEndpoint(): ?string
    {
        return '/runtime';
    }

    protected function resolveBaseUrl(): ?string
    {
        return 'https://runtime.test';
    }
}
