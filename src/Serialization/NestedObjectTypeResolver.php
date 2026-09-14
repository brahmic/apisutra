<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Traversable;

/** @internal Выбирает одиночный DTO; null сохраняет обработку коллекции. */
final readonly class NestedObjectTypeResolver
{
    /** @return class-string|null */
    public function resolve(Nested $nested, ReflectionProperty $property): ?string
    {
        if ($nested->type !== null) {
            $this->assertInstantiable($nested->type);
        }

        $type = $property->getType();
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : ($type === null ? [] : [$type]);
        $names = [];
        foreach ($types as $part) {
            if (!$part instanceof ReflectionNamedType) {
                throw new ConfigurationException('Nested не поддерживает intersection: ' . $property->getName());
            }
            if ($part->getName() !== 'null') {
                $names[] = match ($part->getName()) {
                    'self' => $property->getDeclaringClass()->getName(),
                    'parent' => ($parent = $property->getDeclaringClass()->getParentClass()) !== false
                        ? $parent->getName()
                        : throw new ConfigurationException('Не найден parent для Nested: ' . $property->getName()),
                    default => $part->getName(),
                };
            }
        }

        if (count($names) > 1) {
            foreach ($names as $name) {
                if (!class_exists($name) && !interface_exists($name)) {
                    throw new ConfigurationException('Неоднозначная кардинальность Nested: ' . $property->getName());
                }
            }
            if ($nested->type === null) {
                throw new ConfigurationException('Для union-свойства необходим Nested.type: ' . $property->getName());
            }
        }

        $propertyType = $names[0] ?? null;
        if ($propertyType === null || in_array($propertyType, ['array', 'iterable', 'mixed'], true)) {
            return null;
        }

        $targetType = $nested->type ?? $propertyType;
        $compatible = $propertyType === 'object';
        foreach ($names as $name) {
            $compatible = $compatible || is_a($targetType, $name, true);
        }

        if (count($names) === 1 && class_exists($propertyType)) {
            if (is_a($propertyType, Traversable::class, true)) {
                return null;
            }
            // Сохраняем пользовательские обёртки списка с конструктором от массива.
            $hasFactory = is_callable([$propertyType, 'fromArray']);
            if (
                (!$compatible && ($hasFactory || $this->acceptsItems($propertyType)))
                || ($hasFactory && $nested->type === null && $nested->map !== null)
            ) {
                return null;
            }
        }

        if (!$compatible) {
            throw new ConfigurationException('Nested.type несовместим с типом свойства: ' . $property->getName());
        }
        if (
            $nested->each !== null || $nested->itemCast !== null
            || $nested->map !== null || $nested->discriminator !== null
        ) {
            throw new ConfigurationException(
                'Параметры списка Nested заданы для одиночного объекта: ' . $property->getName(),
            );
        }

        $this->assertInstantiable($targetType);

        return $targetType;
    }

    private function assertInstantiable(string $type): void
    {
        if (!class_exists($type) || !(new ReflectionClass($type))->isInstantiable()) {
            throw new ConfigurationException('Недоступен класс Nested для гидратации: ' . $type);
        }
    }

    /** @param class-string $type */
    private function acceptsItems(string $type): bool
    {
        $constructor = (new ReflectionClass($type))->getConstructor();
        if ($constructor === null || !$constructor->isPublic() || $constructor->getNumberOfRequiredParameters() > 1) {
            return false;
        }
        $parameter = $constructor->getParameters()[0] ?? null;
        if ($parameter === null) {
            return false;
        }
        $parameterType = $parameter->getType();
        if ($parameterType === null) {
            return true;
        }
        $types = $parameterType instanceof ReflectionUnionType ? $parameterType->getTypes() : [$parameterType];
        foreach ($types as $part) {
            if (
                $part instanceof ReflectionNamedType
                && in_array($part->getName(), ['array', 'iterable', 'mixed'], true)
            ) {
                return true;
            }
        }

        return false;
    }
}
