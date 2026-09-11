<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Serialization;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Serialization\VO\RequestPartsBag;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

interface RequestPartsEnricherInterface
{
    public function enrich(
        RequestInterface $request,
        RequestPartsBag $parts,
        ?PipelineContext $context = null,
    ): RequestPartsBag;
}
