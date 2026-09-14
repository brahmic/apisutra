<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class StrictListCast implements CastInterface
{
    /** @param class-string|null $itemType */
    public function __construct(private ?string $itemType = null)
    {
    }

    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw HydrationException::invalidValue('invalid_list_shape', 'list', get_debug_type($value));
        }

        return $this->itemType === null ? $value : Hydrator::default()->hydrateCollection($value, $this->itemType, $context);
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
