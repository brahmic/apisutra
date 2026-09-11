<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Casts;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Enums\Serialization\BooleanFormat;
use Brahmic\ApiSutra\Exceptions\Serialization\SerializationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Override;

final class BooleanCast implements CastInterface
{
    /** Без формата сохраняется исходное приведение в bool, включая DTO. */
    public function __construct(private readonly ?BooleanFormat $format = null) {}

    #[Override]
    public function hydrate(mixed $value, ?PipelineContext $context = null): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
        }

        $normalized = strtolower((string) $value);
        return in_array($normalized, ['1', 'true', 'yes', 'y'], true);
    }

    #[Override]
    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        if ($this->format === null || $value === null) {
            return $value === null ? null : (bool) $value;
        }
        if (!is_bool($value)) {
            throw new SerializationException('Текстовый BooleanCast ожидает bool или null');
        }

        return $this->format->format($value);
    }
}
