<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Attributes\ContextProbeAttribute;

#[Get('/context-probe')]
#[ContextProbeAttribute('class-a')]
#[ContextProbeAttribute('class-b')]
final class ContextProbeRequest extends AbstractRequest
{
    #[ContextProbeAttribute('property')]
    public string $payload = 'value';
}
