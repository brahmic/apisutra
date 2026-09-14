<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\Rules;

use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use UnitEnum;

/** @internal Проверка неизменяемых аргументов декларации без хранения объектов исполнения. */
final readonly class LiteralValues
{
    public static function assert(mixed $value, int $depth = 0): void
    {
        if ($depth > 512) {
            throw new ConfigurationException('Слишком глубокое или рекурсивное значение декларации');
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                self::assert($item, $depth + 1);
            }
            return;
        }
        if ($value !== null && !is_scalar($value) && !$value instanceof UnitEnum) {
            throw new ConfigurationException('Декларация допускает только scalar, null, enum и массивы значений');
        }
    }
}
