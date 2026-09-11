<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

final class AttributeMetadataCache
{
    /**
     * @var array<string, array>
     */
    private array $cache = [];

    public function __construct(
        private readonly bool $enabled = true,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function get(string $class): ?array
    {
        return $this->enabled ? ($this->cache[$class] ?? null) : null;
    }

    public function set(string $class, array $data): void
    {
        if ($this->enabled) {
            $this->cache[$class] = $data;
        }
    }

    /**
     * Предварительно заполнить кеш метаданными
     * @param array<int, class-string> $classes
     */
    public function warmup(array $classes): void
    {
        if (!$this->enabled) {
            return;
        }

        foreach ($classes as $class) {
            if (!class_exists($class) || isset($this->cache[$class])) {
                continue;
            }

            $this->cache[$class] = $this->scan(new ReflectionClass($class));
        }
    }

    /**
     * @return array{class: array<int, array{attribute: ReflectionAttribute}>, properties: array<int, array{attribute: ReflectionAttribute, property: ReflectionProperty}>}
     */
    private function scan(ReflectionClass $reflection): array
    {
        $classAttributes = array_map(
            static fn (ReflectionAttribute $attribute): array => ['attribute' => $attribute],
            $reflection->getAttributes(),
        );

        $propertyAttributes = [];
        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes() as $attribute) {
                $propertyAttributes[] = [
                    'attribute' => $attribute,
                    'property' => $property,
                ];
            }
        }

        return [
            'class' => $classAttributes,
            'properties' => $propertyAttributes,
        ];
    }
}
