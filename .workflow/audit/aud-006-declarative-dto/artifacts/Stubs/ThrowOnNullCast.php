<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class ThrowOnNullCast implements CastInterface
{
    public static int $calls = 0;

    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        self::$calls++;
        if ($value === null) {
            throw HydrationException::invalidValue('explicit_null_not_allowed', 'non-null', 'null');
        }

        return $value;
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
