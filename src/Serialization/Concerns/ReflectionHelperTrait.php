<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\Concerns;

use Brahmic\ApiSutra\Serialization\PropertyTypeInspector;
use ReflectionProperty;

trait ReflectionHelperTrait
{
    private function getAttribute(ReflectionProperty $property, string $class): ?object
    {
        return ($property->getAttributes($class)[0] ?? null)?->newInstance();
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
