<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class IdentityCast implements CastInterface
{
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
