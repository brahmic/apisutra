<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Enrichment;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\RequestPartsEnricherInterface;
use Brahmic\ApiSutra\Serialization\VO\RequestPartsBag;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class TestRequestPartsEnricher implements RequestPartsEnricherInterface
{
    public function __construct(
        private string $queryKey,
        private string $queryValue,
    ) {}

    #[\Override]
    public function enrich(
        RequestInterface $request,
        RequestPartsBag $parts,
        ?PipelineContext $context = null,
    ): RequestPartsBag {
        $parts->query[$this->queryKey] = [
            'value' => $this->queryValue,
            'format' => null,
        ];

        return $parts;
    }
}
