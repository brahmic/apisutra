<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ReviewChildCast implements CastInterface
{
    /** @param class-string $type */
    public function __construct(private string $type)
    {
    }

    public function hydrate(mixed $value, ?PipelineContext $context = null): object
    {
        if (!is_array($value) && !is_object($value)) {
            throw HydrationException::invalidValue('unexpected_response_shape', $this->type, get_debug_type($value));
        }
        return Hydrator::default()->hydrate($value, $this->type, $context);
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
