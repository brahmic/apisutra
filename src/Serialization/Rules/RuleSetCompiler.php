<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\Rules;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeTo;
use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrate;
use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use Brahmic\ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\Map;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Collections\AbstractTypedCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use Brahmic\ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use DateTimeInterface;
use JsonSerializable;
use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Stringable;

/** @internal Проверяет декларации, не создавая DTO, атрибутов или обработчиков. */
final class RuleSetCompiler
{
    private const array INPUT_ATTRIBUTES = [
        From::class, Map::class, Nested::class, Cast::class,
        DateTimeFrom::class, EmptyStringAsNull::class, DefaultValue::class,
    ];
    private const array OUTPUT_ATTRIBUTES = [
        To::class, DateTimeTo::class, Query::class, Body::class, BodyRoot::class,
        Header::class, Path::class, File::class,
    ];

    /** @var array<class-string, CompiledDtoRules> */
    private array $compiled = [];

    public function __construct(private readonly HydrationRules $rules)
    {
        $this->validatePolicy($rules->defaults());
        foreach ($rules->definitions() as $class => $declaration) {
            $this->forClass($class);
        }
    }

    public function forClass(string $class): CompiledDtoRules
    {
        if (isset($this->compiled[$class])) {
            return $this->compiled[$class];
        }
        if (!class_exists($class)) {
            throw new ConfigurationException('Класс DTO набора не найден: ' . $class);
        }
        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new ConfigurationException('Класс DTO набора недоступен для создания: ' . $class);
        }
        $declaration = $this->rules->rulesFor($class);
        $profile = $this->hasProfile($reflection);
        if ($profile && $declaration !== null) {
            throw new ConfigurationException('DtoRules конфликтует с DtoHydrate/DtoHydrationProfile: ' . $class);
        }
        $policy = $profile ? new RulePolicy() : ($declaration->policy ?? new RulePolicy())->over($this->rules->defaults());
        $compiled = new CompiledDtoRules($reflection, $declaration, $policy, $profile);
        // Запись до рекурсивных ссылок разрешает Node → Node без повторного обхода.
        $this->compiled[$class] = $compiled;
        try {
            $this->validatePolicy($policy);
            foreach ($declaration->fields ?? [] as $name => $field) {
                $property = $this->property($reflection, $name);
                $this->rejectAttributes($property, self::INPUT_ATTRIBUTES);
                if ($field->policy !== null) {
                    $this->validatePolicy($field->policy);
                }
                if ($field->from === '' || in_array('', $field->fallback, true)) {
                    throw new ConfigurationException('Путь FieldRule.from/fallback не должен быть пустым');
                }
                if ($field->cast !== null) {
                    $this->validateHandler($field->cast, CastInterface::class);
                }
                if ($field->default?->provider !== null) {
                    $this->validateHandler($field->default->provider, DefaultValueProviderInterface::class);
                }
                if ($field->constructorValue) {
                    $this->validateConstructorValue($reflection, $property, $field);
                }
                if ($field->shape !== null) {
                    $this->validateShape($field->shape, $property);
                }
            }
            if ($declaration?->receiver !== null) {
                $this->validateReceiver($reflection, $declaration);
            }
        } catch (ConfigurationException $exception) {
            unset($this->compiled[$class]);
            throw $exception;
        }
        return $compiled;
    }

    private function validateConstructorValue(ReflectionClass $class, ReflectionProperty $property, FieldRule $field): void
    {
        $constructor = $class->getConstructor();
        if (
            !$property->isPublic() || $property->hasHooks() || $property->hasDefaultValue()
            || $constructor === null || !$constructor->isPublic()
            || array_any($constructor->getParameters(), static fn (ReflectionParameter $parameter): bool => $parameter->getName() === $property->getName())
            || !$this->isConstructorValueType($property->getType())
        ) {
            throw new ConfigurationException('constructorValue требует отдельное public поле поддержанного типа без hooks/default: '
                . $class->getName() . '::$' . $property->getName());
        }
        for ($shape = $field->shape; $shape !== null; $shape = $shape->item) {
            if (!in_array($shape->kind, ['scalar', 'mixed', 'nullable', 'list'], true)) {
                throw new ConfigurationException('constructorValue не поддерживает DTO/variants в форме значения');
            }
        }
    }

    private function isConstructorValueType(?ReflectionType $type): bool
    {
        if ($type instanceof ReflectionUnionType) {
            return array_all($type->getTypes(), fn (ReflectionType $part): bool => $this->isConstructorValueType($part));
        }
        return $type instanceof ReflectionNamedType && (
            in_array($type->getName(), ['int', 'float', 'string', 'bool', 'true', 'false', 'null', 'array'], true)
            || enum_exists($type->getName())
        );
    }

    /** @return array<class-string, string> */
    public function receivers(): array
    {
        $result = [];
        foreach ($this->rules->definitions() as $class => $declaration) {
            if ($declaration->receiver !== null) {
                $result[$class] = $declaration->receiver;
            }
        }
        return $result;
    }

    private function hasProfile(ReflectionClass $class): bool
    {
        do {
            if ($class->getAttributes(DtoHydrate::class) !== [] || $class->getAttributes(DtoHydrationProfile::class) !== []) {
                return true;
            }
            $class = $class->getParentClass();
        } while ($class !== false);
        return false;
    }

    private function property(ReflectionClass $class, string $name): ReflectionProperty
    {
        if (!$class->hasProperty($name)) {
            throw new ConfigurationException('Свойство правила не найдено: ' . $class->getName() . '::$' . $name);
        }
        $property = $class->getProperty($name);
        if ($property->isStatic() || $property->isVirtual()) {
            throw new ConfigurationException('Правило требует хранимое свойство DTO: ' . $class->getName() . '::$' . $name);
        }
        return $property;
    }

    /** @param list<class-string> $attributes */
    private function rejectAttributes(ReflectionProperty $property, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            if ($property->getAttributes($attribute) !== []) {
                throw new ConfigurationException(
                    'Конфликт правила и атрибута ' . $attribute . ': '
                    . $property->getDeclaringClass()->getName() . '::$' . $property->getName(),
                );
            }
        }
    }

    private function validateReceiver(ReflectionClass $class, DtoRules $rules): void
    {
        $name = $rules->receiver;
        $property = $this->property($class, $name);
        $type = $property->getType();
        if (!$property->isPublic() || !$type instanceof ReflectionNamedType || $type->getName() !== 'array') {
            throw new ConfigurationException('Receiver требует public array или ?array: ' . $class->getName() . '::$' . $name);
        }
        if (isset($rules->fields[$name])) {
            throw new ConfigurationException('Receiver не может иметь FieldRule: ' . $class->getName() . '::$' . $name);
        }
        $this->rejectAttributes($property, [...self::INPUT_ATTRIBUTES, ...self::OUTPUT_ATTRIBUTES]);
        $constructor = $class->getConstructor();
        if (
            $constructor !== null && !array_any(
                $constructor->getParameters(),
                static fn (ReflectionParameter $parameter): bool => $parameter->getName() === $name,
            )
        ) {
            throw new ConfigurationException('Receiver должен быть параметром конструктора: ' . $class->getName() . '::$' . $name);
        }
        if (
            $class->implementsInterface(JsonSerializable::class)
            || $class->implementsInterface(DateTimeInterface::class)
            || $class->implementsInterface(Stringable::class)
            || ($class->hasMethod('toArray') && !$class->implementsInterface(DtoInterface::class))
        ) {
            throw new ConfigurationException('Receiver несовместим с собственным JSON-представлением: ' . $class->getName());
        }
    }

    private function validatePolicy(RulePolicy $policy): void
    {
        foreach ($policy->casts as $spec) {
            $this->validateHandler($spec, CastInterface::class);
        }
    }

    private function validateHandler(HandlerSpec $spec, string $interface): void
    {
        if (!is_subclass_of($spec->class, $interface)) {
            throw new ConfigurationException('Неверный обработчик правила: ' . $spec->class);
        }
        $reflection = new ReflectionClass($spec->class);
        if (!$reflection->isInstantiable()) {
            throw new ConfigurationException('Обработчик правила недоступен для создания: ' . $spec->class);
        }
    }

    private function validateShape(ValueShape $shape, ReflectionProperty $property, bool $listItem = false): void
    {
        if ($shape->kind === 'dto') {
            $this->forClass($shape->class);
        }
        if ($shape->kind === 'variants') {
            if (!$listItem || $shape->map === []) {
                throw new ConfigurationException('Variants требует непустую map и позицию элемента list');
            }
            if ($shape->mode === NestedDiscriminatorMode::Value && $shape->discriminator === '') {
                throw new ConfigurationException('Value-discriminator требует непустой путь');
            }
            $type = $property->getType();
            if (
                $shape->unknown === NestedUnknownVariant::KeepRaw
                && $type instanceof ReflectionNamedType
                && is_subclass_of($type->getName(), AbstractTypedCollection::class)
            ) {
                throw new ConfigurationException('KeepRaw несовместим с typed collection DTO');
            }
            foreach ($shape->map as $class) {
                $this->compileReference($class);
            }
        }
        if ($shape->itemCast !== null) {
            $this->validateHandler($shape->itemCast, CastInterface::class);
        }
        if ($shape->item !== null) {
            $this->validateShape($shape->item, $property, $shape->kind === 'list' || $listItem);
        }
    }

    private function compileReference(mixed $class): void
    {
        if (!is_string($class)) {
            throw new ConfigurationException('Variants.map требует классы DTO');
        }
        $this->forClass($class);
    }
}
