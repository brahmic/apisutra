<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/wire')]
final class QueryCastRequest extends AbstractRequest
{
    public function __construct(#[Query] #[Cast(OpaqueCast::class)] public mixed $payload)
    {
    }
}
