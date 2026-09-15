<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Exceptions\Serialization\SerializationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

// Учебный формат: неотрицательная сумма, до семи цифр перед точкой и ровно две после.
final readonly class MinorUnitsCast implements CastInterface
{
    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        if (!is_string($value) || preg_match('/^([0-9]{1,7})\.([0-9]{2})$/D', $value, $parts) !== 1) {
            throw HydrationException::invalidValue(
                'invalid_price',
                'decimal string with 2 digits',
                get_debug_type($value),
            );
        }

        // Целочисленная арифметика сохраняет точность исходной строки.
        return (int) $parts[1] * 100 + (int) $parts[2];
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        if (!is_int($value) || $value < 0 || $value > 999_999_999) {
            throw new SerializationException('Ожидается целое число в диапазоне учебного формата цены');
        }

        return intdiv($value, 100) . '.' . str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }
}
