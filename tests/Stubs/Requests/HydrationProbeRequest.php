<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Override;

#[Get('/hydration-probe')]
final class HydrationProbeRequest extends AbstractRequest
{
    public function __construct(private readonly string $dtoType) {}

    #[Override]
    public function getResponseType(): ?string
    {
        return $this->dtoType;
    }
}
