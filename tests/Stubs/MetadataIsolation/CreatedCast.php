<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class CreatedCast implements CastInterface
{
    /** @param array<string, list<CreatedValue>> $values */
    public function __construct(private array $values)
    {
    }

    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        $counter = $this->values['nested'][0];

        return ++$counter->value;
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        $counter = $this->values['nested'][0];

        return ++$counter->value;
    }
}
