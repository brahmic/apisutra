<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\Concerns;

use Brahmic\ApiSutra\Serialization\PropertyTypeInspector;
use ReflectionAttribute;
use ReflectionProperty;
use UnitEnum;

trait ReflectionHelperTrait
{
    /**
     * @param array<string, class-string> $classes
     * @param array<string, ReflectionAttribute> $factories
     * @return array<string, object|null>
     */
    private function getPropertyAttributes(ReflectionProperty $property, array $classes, array &$factories): array
    {
        $attributes = [];
        foreach ($classes as $key => $class) {
            $declaration = $property->getAttributes($class)[0] ?? null;
            $instance = $declaration?->newInstance();
            $attributes[$key] = $instance;
            // Приведение раскрывает также private/protected свойства, но не вызывает пользовательские методы.
            if ($instance !== null && $this->containsAttributeObject((array) $instance)) {
                $factories[$key] = $declaration;
            }
        }

        return $attributes;
    }

    /** @param array<mixed> $values */
    private function containsAttributeObject(array $values): bool
    {
        foreach ($values as $value) {
            if (is_object($value) && !$value instanceof UnitEnum) {
                return true;
            }
            if (is_array($value) && $this->containsAttributeObject($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $properties
     * @param array<int, array<string, ReflectionAttribute>> $factories
     * @return array<int, array<string, mixed>>
     */
    private function resolvePropertyAttributes(array $properties, array $factories, bool $materialize): array
    {
        foreach ($factories as $index => $attributes) {
            foreach ($attributes as $key => $declaration) {
                $properties[$index][$key] = $materialize ? $declaration->newInstance() : null;
            }
        }

        return $properties;
    }

    private function getPrimaryType(ReflectionProperty $property): ?string
    {
        return $this->propertyTypeInspector()->getPrimaryType($property);
    }

    /**
     * @return array<int, string>
     */
    private function getPropertyTypes(ReflectionProperty $property): array
    {
        return $this->propertyTypeInspector()->getPropertyTypes($property);
    }

    private function resolvePropertyTypeByValue(ReflectionProperty $property, mixed $value): ?string
    {
        return $this->propertyTypeInspector()->resolvePropertyTypeByValue($property, $value);
    }

    private function matchesRuntimeType(mixed $value, string $type): bool
    {
        return $this->propertyTypeInspector()->matchesRuntimeType($value, $type);
    }

    private function propertyTypeInspector(): PropertyTypeInspector
    {
        static $inspector = null;

        return $inspector ??= new PropertyTypeInspector();
    }
}
