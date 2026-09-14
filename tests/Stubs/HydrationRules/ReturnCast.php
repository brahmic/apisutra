<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ReturnCast implements CastInterface
{
    public function __construct(private mixed $result)
    {
    }

    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $this->result;
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $this->result;
    }
}
