<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Collections;

use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * @template T of object
 * @extends AbstractCollection<T>
 */
abstract readonly class AbstractTypedCollection extends AbstractCollection
{
    /**
     * @return class-string<T>
     */
    abstract protected static function itemClass(): string;

    /**
     * @param array<int, mixed> $items
     */
    protected function validateItems(array $items): void
    {
        $class = static::itemClass();
        if ($class === '') {
            throw new ConfigurationException('Тип элемента коллекции не задан');
        }

        if (!class_exists($class) && !enum_exists($class)) {
            throw new ConfigurationException('Класс элемента коллекции не найден: ' . $class);
        }

        foreach ($items as $item) {
            if (!$item instanceof $class) {
                throw new ConfigurationException('Элемент коллекции имеет неверный тип: ' . $class);
            }
        }
    }
}
