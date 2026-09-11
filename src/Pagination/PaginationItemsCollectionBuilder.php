<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pagination;

use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsCollectionFactoryInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * Создаёт коллекцию items по конфигу пагинации.
 * Порядок разрешения: factory → collection class → массив.
 */
final readonly class PaginationItemsCollectionBuilder
{
    /**
     * @param array<int, mixed> $items
     */
    public function build(array $items, PaginationConfig $config): array|object
    {
        // Сначала отдаём приоритет фабрике, чтобы контейнер создавался контролируемо
        $fromFactory = $this->buildFromFactory($items, $config);
        if ($fromFactory !== null) {
            return $fromFactory;
        }

        // Затем пробуем сам класс коллекции
        $fromClass = $this->buildFromCollectionClass($items, $config);
        if ($fromClass !== null) {
            return $fromClass;
        }

        return $items;
    }

    /**
     * Приводит items к массиву для агрегации.
     *
     * @return array<int, mixed>
     */
    public function normalizeToArray(mixed $items): array
    {
        if ($items === null) {
            return [];
        }

        if (is_array($items)) {
            return $items;
        }

        if (is_object($items) && method_exists($items, 'toArray')) {
            $array = $items->toArray();
            if (is_array($array)) {
                return $array;
            }
        }

        if (is_iterable($items)) {
            $result = [];
            foreach ($items as $item) {
                $result[] = $item;
            }
            return $result;
        }

        throw new ConfigurationException('Items должны быть массивом или коллекцией');
    }

    /**
     * @param array<int, mixed> $items
     */
    private function buildFromFactory(array $items, PaginationConfig $config): array|object|null
    {
        if ($config->itemsCollectionFactory === null) {
            return null;
        }

        $factory = $config->itemsCollectionFactory;
        if (is_string($factory)) {
            if (!class_exists($factory)) {
                throw new ConfigurationException("Класс фабрики '{$factory}' не найден");
            }
            $factory = new $factory();
        }

        if (!$factory instanceof PaginationItemsCollectionFactoryInterface) {
            throw new ConfigurationException('Фабрика коллекции должна реализовывать PaginationItemsCollectionFactoryInterface');
        }

        return $factory->make($items);
    }

    /**
     * @param array<int, mixed> $items
     */
    private function buildFromCollectionClass(array $items, PaginationConfig $config): array|object|null
    {
        if ($config->itemsCollection === null) {
            return null;
        }

        $class = $config->itemsCollection;
        if (!class_exists($class)) {
            throw new ConfigurationException("Класс коллекции '{$class}' не найден");
        }

        if (method_exists($class, 'fromArray')) {
            return $class::fromArray($items);
        }

        if (method_exists($class, 'make')) {
            return $class::make($items);
        }

        try {
            return new $class($items);
        } catch (\Throwable $exception) {
            throw new ConfigurationException('Невозможно создать коллекцию items', previous: $exception);
        }
    }
}
