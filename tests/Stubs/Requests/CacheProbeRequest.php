<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Override;

#[Get('/items')]
final class CacheProbeRequest extends AbstractRequest
{
    /** @param list<int> $ids */
    public function __construct(
        private readonly string $endpoint = '/items',
        #[Query] public array $ids = [],
    ) {}

    #[Override]
    protected function resolveEndpoint(): ?string
    {
        return $this->endpoint;
    }
}
