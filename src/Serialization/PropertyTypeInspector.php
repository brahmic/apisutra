<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

final readonly class PropertyTypeInspector
{
    public function getPrimaryType(ReflectionProperty $property): ?string
    {
        return $this->getPropertyTypes($property)[0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function getPropertyTypes(ReflectionProperty $property): array
    {
        $type = $property->getType();
        if ($type instanceof ReflectionNamedType) {
            return $type->getName() === 'null' ? [] : [$type->getName()];
        }

        if ($type instanceof ReflectionUnionType) {
            $resolved = array_filter(
                array_map(
                    static fn (ReflectionNamedType $subType): string => $subType->getName(),
                    $type->getTypes(),
                ),
                static fn (string $name): bool => $name !== 'null',
            );

            return array_values($resolved);
        }

        return [];
    }

    public function resolvePropertyTypeByValue(ReflectionProperty $property, mixed $value): ?string
    {
        $types = $this->getPropertyTypes($property);
        if ($types === []) {
            return null;
        }

        $resolved = array_find(
            $types,
            fn (string $type): bool => $this->matchesRuntimeType($value, $type),
        );

        return $resolved ?? $types[0];
    }

    public function matchesRuntimeType(mixed $value, string $type): bool
    {
        return match ($type) {
            'mixed' => true,
            'bool' => is_bool($value),
            'true' => $value === true,
            'false' => $value === false,
            'int' => is_int($value),
            'float' => is_float($value),
            'string' => is_string($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'callable' => is_callable($value),
            'iterable' => is_iterable($value),
            default => is_object($value) && is_a($value, $type),
        };
    }
}
