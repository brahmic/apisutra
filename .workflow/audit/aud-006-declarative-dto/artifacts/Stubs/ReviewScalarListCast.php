<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ReviewScalarListCast implements CastInterface
{
    public function __construct(private string $type)
    {
    }

    /** @return list<int|bool|string> */
    public function hydrate(mixed $value, ?PipelineContext $context = null): array
    {
        $items = (new StrictListCast())->hydrate($value, $context);
        $cast = new StrictScalarCast($this->type);
        foreach ($items as $index => $item) {
            try {
                $items[$index] = $cast->hydrate($item, $context);
            } catch (HydrationException $error) {
                throw $error->prependPath('[' . $index . ']');
            }
        }
        return $items;
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
