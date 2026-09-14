<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class RowCast implements CastInterface
{
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        return (new StrictListCast(PlainScalarDto::class))->hydrate($value, $context);
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
