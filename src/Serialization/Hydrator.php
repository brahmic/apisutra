<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use Brahmic\ApiSutra\Attributes\AttributeMetadataCache;
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast as CastAttribute;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\Map;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Collections\AbstractTypedCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use Brahmic\ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use Brahmic\ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Serialization\Concerns\ReflectionHelperTrait;
use Brahmic\ApiSutra\Serialization\VO\ResolvedDtoHydration;
use Brahmic\ApiSutra\Support\ArrayPath;
use Brahmic\ApiSutra\Support\PathResult;
use Brahmic\ApiSutra\VO\Files\Base64File;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use JsonSerializable;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

final class Hydrator
{
    use ReflectionHelperTrait;

    private static ?self $default = null;
    private readonly NamingStrategyResolver $namingStrategyResolver;
    private readonly BuiltinHydrationCaster $builtinHydrationCaster;

    public function __construct(
        private readonly CastRegistry $casts,
        private readonly ?AttributeMetadataCache $cache = null,
        private readonly ?DtoHydrationProfileResolver $profileResolver = null,
    ) {
        $this->namingStrategyResolver = new NamingStrategyResolver();
        $this->builtinHydrationCaster = new BuiltinHydrationCaster(
            typeSelector: new HydrationTypeSelector(),
            dtoHydrator: fn (mixed $nestedValue, string $dtoClass, ?PipelineContext $nestedContext): object => $this->hydrate($nestedValue, $dtoClass, $nestedContext),
        );
    }

    /**
     * Синглтон для DTO::from() без явного контекста
     * Использует CastRegistry::global()
     */
    public static function default(): self
    {
        return self::$default ??= new self(
            CastRegistry::global(),
        );
    }

    /**
     * Гидрировать данные в DTO
     */
    public function hydrate(
        array|object $data,
        string $dtoClass,
        ?PipelineContext $context = null,
    ): object {
        $array = $this->normalizeData($data);

        if (is_subclass_of($dtoClass, ResponseDtoInterface::class)) {
            $array = $dtoClass::computed($array, $context);
        }

        $metadata = $this->getHydrationMetadata($dtoClass);
        $resolvedHydration = $this->resolveHydration($dtoClass);
        $values = [];

        foreach ($metadata['properties'] as $propertyMeta) {
            $name = $propertyMeta['name'];
            $from = $propertyMeta['from'];
            $map = $propertyMeta['map'];
            $nested = $propertyMeta['nested'];
            $cast = $propertyMeta['cast'];
            $dateTimeFrom = $propertyMeta['dateTimeFrom'];
            $emptyStringAsNull = $propertyMeta['emptyStringAsNull'];
            $default = $propertyMeta['default'];
            $property = $propertyMeta['property'];

            $key = $from?->name
                ?? $map?->name
                ?? $this->namingStrategyResolver->resolveByStrategy($name, $resolvedHydration->policy->namingStrategy);
            $primaryPath = $nested?->from ?? $key;
            $fallbacks = $nested?->fallback ?? [];
            if ($fallbacks === [] && $from !== null) {
                $fallbacks = $from->fallback;
            }
            $resolved = $this->resolveValueWithFallbacks($array, $primaryPath, $fallbacks);
            $state = $resolved->state;
            $value = $resolved->value;

            [$state, $value, $emptyStringNormalized] = $this->normalizeEmptyStringValue(
                state: $state,
                value: $value,
                cast: $cast,
                emptyStringAsNull: $emptyStringAsNull,
                resolvedHydration: $resolvedHydration,
                property: $property,
                dtoClass: $dtoClass,
            );

            if ($default !== null && $this->shouldApplyDefault($default, $state)) {
                $value = $this->resolveDefaultValue($default, $value, $state, $array, $context);
                $state = $value === null ? ValueState::Null : ValueState::Present;
            }

            if ($emptyStringNormalized && $value === null && !$property->getType()?->allowsNull()) {
                throw new ConfigurationException(
                    'Нормализация empty string в null несовместима с non-nullable DTO property: '
                    . $dtoClass . '::$' . $property->getName(),
                );
            }

            if ($state === ValueState::Missing && $this->shouldAutoDefaultEmptyTypedCollection($property, $default, $state)) {
                $values[$name] = $this->wrapCollection([], $this->getPrimaryType($property));
                continue;
            }

            if ($state === ValueState::Missing) {
                continue;
            }

            try {
                if ($nested !== null) {
                    $value = $this->hydrateNested($value, $nested, $property, $context);
                } else {
                    $value = $this->applyCasts($value, $cast, $dateTimeFrom, $property, $resolvedHydration, $context);
                }
            } catch (HydrationException $exception) {
                throw $exception->prependPath($name);
            }

            $values[$name] = $value;
        }

        $reflection = new ReflectionClass($dtoClass);
        $constructor = $metadata['constructor'];
        if ($constructor === null) {
            return $this->instantiateWithoutConstructorMetadata($reflection, $metadata['properties'], $values);
        }

        $constructorArgs = $this->buildConstructorArguments($values, $constructor);
        $remainingAssignments = $this->buildRemainingPropertyAssignments(
            values: $values,
            properties: $metadata['properties'],
            constructor: $constructor,
        );

        if ($remainingAssignments === []) {
            return $reflection->newInstanceArgs($constructorArgs);
        }

        return $this->instantiateWithPropertyFill(
            reflection: $reflection,
            constructorArgs: $constructorArgs,
            assignments: $remainingAssignments,
        );
    }

    /**
     * Гидрировать массив в коллекцию DTO
     * @return array<int, object>
     */
    public function hydrateCollection(
        array $items,
        string $dtoClass,
        ?PipelineContext $context = null,
    ): array {
        $result = [];
        foreach ($items as $item) {
            $result[] = $this->hydrateItem($item, $dtoClass, $context, count($result));
        }

        return $result;
    }

    private function hydrateItem(mixed $item, string $dtoClass, ?PipelineContext $context, int $index): object
    {
        try {
            return $this->hydrate($item, $dtoClass, $context);
        } catch (HydrationException $exception) {
            throw $exception->prependPath('[' . $index . ']');
        }
    }

    private function normalizeData(array|object $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (method_exists($data, 'toArray')) {
            return (array) $data->toArray();
        }

        if ($data instanceof JsonSerializable) {
            return (array) $data->jsonSerialize();
        }

        return (array) $data;
    }

    private function applyCasts(
        mixed $value,
        ?CastAttribute $cast,
        ?DateTimeFrom $dateTimeFrom,
        ReflectionProperty $property,
        ResolvedDtoHydration $resolvedHydration,
        ?PipelineContext $context,
    ): mixed {
        return $this->builtinHydrationCaster->hydrate(
            value: $value,
            cast: $cast,
            dateTimeFrom: $dateTimeFrom,
            property: $property,
            resolved: $resolvedHydration,
            context: $context,
        );
    }

    private function resolveHydration(string $dtoClass): ResolvedDtoHydration
    {
        $resolver = $this->profileResolver ?? new DtoHydrationProfileResolver();

        return $resolver->resolveForDto($dtoClass);
    }

    /**
     * @param array<string, mixed> $values
     * @param array<int, array{name: string, hasDefault: bool, default: mixed}> $constructor
     * @return array<string, mixed>
     */
    private function buildConstructorArguments(array $values, array $constructor): array
    {
        $args = [];

        foreach ($constructor as $parameter) {
            $paramName = $parameter['name'];

            if (array_key_exists($paramName, $values)) {
                $args[$paramName] = $values[$paramName];
                continue;
            }

            if ($parameter['hasDefault']) {
                $args[$paramName] = $parameter['default'];
            }
        }

        return $args;
    }

    /**
     * @param array<string, mixed> $values
     * @param array<int, array{
     *   name: string,
     *   property: ReflectionProperty,
     *   from: ?From,
     *   map: ?Map,
     *   nested: ?Nested,
     *   cast: ?CastAttribute,
     *   dateTimeFrom: ?DateTimeFrom,
     *   default: ?DefaultValue
     * }> $properties
     * @param array<int, array{name: string, hasDefault: bool, default: mixed}> $constructor
     * @return array<string, array{property: ReflectionProperty, value: mixed}>
     */
    private function buildRemainingPropertyAssignments(array $values, array $properties, array $constructor): array
    {
        $constructorNames = array_fill_keys(
            array_map(
                static fn (array $parameter): string => $parameter['name'],
                $constructor,
            ),
            true,
        );

        $assignments = [];

        foreach ($properties as $propertyMeta) {
            $name = $propertyMeta['name'];

            if (isset($constructorNames[$name])) {
                continue;
            }

            $property = $propertyMeta['property'];

            if (array_key_exists($name, $values)) {
                $assignments[$name] = [
                    'property' => $property,
                    'value' => $values[$name],
                ];
                continue;
            }

            if ($property->getType()?->allowsNull() === true) {
                $assignments[$name] = [
                    'property' => $property,
                    'value' => null,
                ];
                continue;
            }

            if (!$property->hasDefaultValue()) {
                throw new ConfigurationException(
                    'Отсутствует обязательное DTO property вне constructor chain: '
                    . $property->getDeclaringClass()->getName() . '::$' . $name,
                );
            }
        }

        return $assignments;
    }

    /**
     * @param array<int, array{
     *   name: string,
     *   property: ReflectionProperty,
     *   from: ?From,
     *   map: ?Map,
     *   nested: ?Nested,
     *   cast: ?CastAttribute,
     *   dateTimeFrom: ?DateTimeFrom,
     *   default: ?DefaultValue
     * }> $properties
     * @param array<string, mixed> $values
     */
    private function instantiateWithoutConstructorMetadata(
        ReflectionClass $reflection,
        array $properties,
        array $values,
    ): object {
        $assignments = $this->buildRemainingPropertyAssignments(
            values: $values,
            properties: $properties,
            constructor: [],
        );

        if ($assignments === []) {
            return $reflection->newInstance();
        }

        return $this->instantiateWithPropertyFill(
            reflection: $reflection,
            constructorArgs: [],
            assignments: $assignments,
        );
    }

    /**
     * @param array<string, mixed> $constructorArgs
     * @param array<string, array{property: ReflectionProperty, value: mixed}> $assignments
     */
    private function instantiateWithPropertyFill(
        ReflectionClass $reflection,
        array $constructorArgs,
        array $assignments,
    ): object {
        $object = $reflection->newInstanceWithoutConstructor();

        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            $constructor->invokeArgs($object, $constructorArgs);
        }

        $this->assignPropertyValues($object, $assignments);

        return $object;
    }

    /**
     * @param array<string, array{property: ReflectionProperty, value: mixed}> $assignments
     */
    private function assignPropertyValues(object $dto, array $assignments): void
    {
        foreach ($assignments as $name => $assignment) {
            $property = $assignment['property'];

            if ($property->isInitialized($dto)) {
                throw new ConfigurationException(
                    'DTO hydration property already initialized before fallback assignment: '
                    . $dto::class . '::$' . $name,
                );
            }

            try {
                $property->setValue($dto, $assignment['value']);
            } catch (ReflectionException|\Error|\TypeError $exception) {
                throw new ConfigurationException(
                    'Не удалось инициализировать DTO property через hydration fallback: '
                    . $dto::class . '::$' . $name . '. ' . $exception->getMessage(),
                    previous: $exception,
                );
            }
        }
    }

    private function hydrateNested(
        mixed $value,
        Nested $nested,
        ReflectionProperty $property,
        ?PipelineContext $context,
    ): mixed {
        if ($value === null) {
            return null;
        }

        if ($nested->each !== null && is_array($value)) {
            $value = array_map(
                fn (mixed $item) => ArrayPath::getByPath($item, $nested->each),
                $value,
            );
        }

        if ($nested->itemCast !== null && is_array($value)) {
            $value = $this->applyNestedItemCast($value, $nested, $context);
        }

        $propertyType = $this->getPrimaryType($property);
        $targetType = $nested->type ?? $propertyType;

        if (is_array($value) && $this->isDiscriminatedNested($nested)) {
            return $this->hydrateDiscriminatedNested(
                value: $value,
                nested: $nested,
                propertyType: $propertyType,
                context: $context,
            );
        }

        if (is_array($value)) {
            if ($targetType !== null && class_exists($targetType)) {
                $items = [];
                foreach ($value as $item) {
                    if (is_object($item) && is_a($item, $targetType)) {
                        $items[] = $item;
                        continue;
                    }

                    if ($targetType === Base64File::class && is_string($item)) {
                        $items[] = new Base64File($item);
                        continue;
                    }

                    $items[] = $this->hydrateItem($item, $targetType, $context, count($items));
                }

                return $this->wrapCollection($items, $propertyType);
            }

            return $value;
        }

        if ($targetType !== null && class_exists($targetType)) {
            return $this->hydrate($value, $targetType, $context);
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $items
     * @return array<int|string, mixed>
     */
    private function applyNestedItemCast(array $items, Nested $nested, ?PipelineContext $context): array
    {
        $castClass = $nested->itemCast;
        if (!is_string($castClass) || trim($castClass) === '') {
            return $items;
        }

        if (!class_exists($castClass)) {
            throw new ConfigurationException('Nested.itemCast класс не найден: ' . $castClass);
        }

        $cast = new $castClass();
        if (!$cast instanceof CastInterface) {
            throw new ConfigurationException('Nested.itemCast должен реализовывать CastInterface: ' . $castClass);
        }

        $index = 0;
        foreach ($items as $key => $item) {
            try {
                $items[$key] = $cast->hydrate($item, $context);
            } catch (HydrationException $exception) {
                throw $exception->prependPath('[' . $index . ']');
            }
            $index++;
        }

        return $items;
    }

    private function wrapCollection(array $items, ?string $propertyType): mixed
    {
        if ($propertyType === null || $propertyType === 'array') {
            return $items;
        }

        if (class_exists($propertyType)) {
            if (is_callable([$propertyType, 'fromArray'])) {
                return $propertyType::fromArray($items);
            }

            return new $propertyType($items);
        }

        return $items;
    }

    private function isDiscriminatedNested(Nested $nested): bool
    {
        if ($nested->map === null) {
            return false;
        }

        if ($nested->discriminatorMode === NestedDiscriminatorMode::Key) {
            return true;
        }

        return $nested->discriminator !== null;
    }

    private function hydrateDiscriminatedNested(
        array $value,
        Nested $nested,
        ?string $propertyType,
        ?PipelineContext $context,
    ): mixed {
        $items = [];
        $index = -1;

        foreach ($value as $item) {
            $index++;
            [$discriminator, $payload, $rawItem] = $this->resolveDiscriminatorPayload($item, $nested);
            $class = $this->resolveDiscriminatorClass($nested, $discriminator);

            if ($class === null) {
                if ($nested->unknownVariant === NestedUnknownVariant::Skip) {
                    continue;
                }

                if ($nested->unknownVariant === NestedUnknownVariant::Error) {
                    throw new ConfigurationException(
                        sprintf(
                            'Неизвестный вариант Nested discriminator: %s',
                            $discriminator ?? 'null',
                        ),
                    );
                }

                $items[] = $rawItem;
                continue;
            }

            $items[] = $this->hydrateItem($payload, $class, $context, $index);
        }

        return $this->wrapCollection($items, $propertyType);
    }

    /**
     * @return array{0: ?string, 1: mixed, 2: mixed}
     */
    private function resolveDiscriminatorPayload(mixed $item, Nested $nested): array
    {
        return $nested->discriminatorMode === NestedDiscriminatorMode::Key
            ? $this->resolveKeyDiscriminatorPayload($item, $nested)
            : $this->resolveValueDiscriminatorPayload($item, $nested);
    }

    /**
     * @return array{0: ?string, 1: mixed, 2: mixed}
     */
    private function resolveValueDiscriminatorPayload(mixed $item, Nested $nested): array
    {
        $discriminator = null;
        if ($nested->discriminator !== null) {
            $resolved = ArrayPath::getByPath($item, $nested->discriminator);
            if (is_scalar($resolved) || $resolved instanceof \Stringable) {
                $discriminator = (string) $resolved;
            }
        }

        return [$discriminator, $item, $item];
    }

    /**
     * @return array{0: ?string, 1: mixed, 2: mixed}
     */
    private function resolveKeyDiscriminatorPayload(mixed $item, Nested $nested): array
    {
        $rawItem = $item;
        $source = $item;

        if ($nested->discriminator !== null && $nested->discriminator !== '') {
            $source = ArrayPath::getByPath($item, $nested->discriminator);
        }

        if (!is_array($source) || $source === []) {
            return [null, $source, $rawItem];
        }

        $key = array_key_first($source);
        if ($key === null) {
            return [null, $source, $rawItem];
        }

        return [(string) $key, $source[$key], $rawItem];
    }

    private function resolveDiscriminatorClass(Nested $nested, ?string $discriminator): ?string
    {
        if ($discriminator === null || !is_array($nested->map)) {
            return null;
        }

        $class = $nested->map[$discriminator] ?? null;

        return is_string($class) ? $class : null;
    }

    /**
     * @return array{
     *   properties: array<int, array{
     *     name: string,
     *     property: ReflectionProperty,
     *     from: ?From,
     *     map: ?Map,
     *     nested: ?Nested,
     *     cast: ?CastAttribute,
     *     dateTimeFrom: ?DateTimeFrom,
     *     emptyStringAsNull: ?EmptyStringAsNull,
     *     default: ?DefaultValue
     *   }>,
     *   constructor: ?array<int, array{name: string, hasDefault: bool, default: mixed}>
     * }
     */
    private function getHydrationMetadata(string $dtoClass): array
    {
        $cacheKey = $dtoClass . ':hydrator';

        $cached = $this->cache?->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $reflection = new ReflectionClass($dtoClass);
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $properties[] = [
                'name' => $property->getName(),
                'property' => $property,
                'from' => $this->getAttribute($property, From::class),
                'map' => $this->getAttribute($property, Map::class),
                'nested' => $this->getAttribute($property, Nested::class),
                'cast' => $this->getAttribute($property, CastAttribute::class),
                'dateTimeFrom' => $this->getAttribute($property, DateTimeFrom::class),
                'emptyStringAsNull' => $this->getAttribute($property, EmptyStringAsNull::class),
                'default' => $this->getAttribute($property, DefaultValue::class),
            ];
        }

        $constructor = $reflection->getConstructor();
        $params = null;
        if ($constructor !== null) {
            $params = [];
            foreach ($constructor->getParameters() as $parameter) {
                $params[] = [
                    'name' => $parameter->getName(),
                    'hasDefault' => $parameter->isDefaultValueAvailable(),
                    'default' => $parameter->isDefaultValueAvailable()
                        ? $parameter->getDefaultValue()
                        : null,
                ];
            }
        }

        $metadata = [
            'properties' => $properties,
            'constructor' => $params,
        ];

        $this->cache?->set($cacheKey, $metadata);

        return $metadata;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $fallbacks
     */
    private function resolveValueWithFallbacks(array $data, string $primaryPath, array $fallbacks): PathResult
    {
        $resolved = ArrayPath::getByPathWithStatus($data, $primaryPath);
        if (!$resolved->isMissing()) {
            return $resolved;
        }

        foreach ($fallbacks as $fallback) {
            $resolved = ArrayPath::getByPathWithStatus($data, $fallback);
            if (!$resolved->isMissing()) {
                return $resolved;
            }
        }

        return $resolved;
    }

    private function shouldApplyDefault(DefaultValue $default, ValueState $state): bool
    {
        return in_array($state, $default->when, true);
    }

    /**
     * @return array{0: ValueState, 1: mixed, 2: bool}
     */
    private function normalizeEmptyStringValue(
        ValueState $state,
        mixed $value,
        ?CastAttribute $cast,
        ?EmptyStringAsNull $emptyStringAsNull,
        ResolvedDtoHydration $resolvedHydration,
        ReflectionProperty $property,
        string $dtoClass,
    ): array {
        if ($state !== ValueState::Present || !is_string($value) || $cast !== null) {
            return [$state, $value, false];
        }

        $shouldNormalize = $emptyStringAsNull?->matches($value)
            ?? $this->matchesEmptyStringBehavior($value, $resolvedHydration->policy->emptyStringBehavior);

        if (!$shouldNormalize) {
            return [$state, $value, false];
        }

        return [ValueState::Null, null, true];
    }

    private function matchesEmptyStringBehavior(string $value, EmptyStringBehavior $behavior): bool
    {
        return match ($behavior) {
            EmptyStringBehavior::Keep => false,
            EmptyStringBehavior::NullIfEmpty => $value === '',
            EmptyStringBehavior::NullIfBlank => trim($value) === '',
        };
    }

    private function shouldAutoDefaultEmptyTypedCollection(
        ReflectionProperty $property,
        ?DefaultValue $default,
        ValueState $state,
    ): bool {
        if ($state !== ValueState::Missing) {
            return false;
        }

        if ($default !== null && $this->shouldApplyDefault($default, $state)) {
            return false;
        }

        $type = $property->getType();
        if ($type === null || $type->allowsNull()) {
            return false;
        }

        $primaryType = $this->getPrimaryType($property);
        if (!is_string($primaryType) || !class_exists($primaryType)) {
            return false;
        }

        return is_subclass_of($primaryType, AbstractTypedCollection::class);
    }

    /**
     * @param array<string, mixed> $source
     */
    private function resolveDefaultValue(
        DefaultValue $default,
        mixed $value,
        ValueState $state,
        array $source,
        ?PipelineContext $context,
    ): mixed {
        if ($default->value !== null && $default->provider !== null) {
            throw new ConfigurationException('DefaultValue не может иметь одновременно value и provider');
        }

        if ($default->value === null && $default->provider === null) {
            throw new ConfigurationException('DefaultValue должен иметь value или provider');
        }

        if ($default->provider === null) {
            return $default->value;
        }

        if (!class_exists($default->provider) || !is_subclass_of($default->provider, DefaultValueProviderInterface::class)) {
            throw new ConfigurationException('DefaultValue.provider должен реализовывать DefaultValueProviderInterface');
        }

        $provider = new $default->provider();
        return $provider->resolve($value, $state, $source, $context);
    }

}
