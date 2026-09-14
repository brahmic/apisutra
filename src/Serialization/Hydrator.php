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
use Brahmic\ApiSutra\Config\DtoHydrationPolicy;
use Brahmic\ApiSutra\Enums\Configuration\NamingStrategy;
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
use Brahmic\ApiSutra\Serialization\Rules\CompiledDtoRules;
use Brahmic\ApiSutra\Serialization\Rules\HydratedProperty;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\HydrationScope;
use Brahmic\ApiSutra\Serialization\Rules\NestedValueProcessor;
use Brahmic\ApiSutra\Serialization\Rules\InputShape;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\RuleValueProcessor;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarValues;
use Brahmic\ApiSutra\Serialization\Rules\SourceConsumption;
use Brahmic\ApiSutra\Serialization\Rules\SourceLocation;
use Brahmic\ApiSutra\Serialization\Rules\SourcePathKind;
use Brahmic\ApiSutra\Serialization\Rules\RuleSetCompiler;
use Brahmic\ApiSutra\Serialization\VO\ResolvedDtoHydration;
use Brahmic\ApiSutra\Support\ArrayPath;
use Brahmic\ApiSutra\Support\PathResult;
use Brahmic\ApiSutra\VO\Files\Base64File;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use JsonSerializable;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

final class Hydrator
{
    use ReflectionHelperTrait;

    private static ?self $default = null;
    private readonly NamingStrategyResolver $namingStrategyResolver;
    private readonly BuiltinHydrationCaster $builtinHydrationCaster;
    private readonly HydrationValueValidator $valueValidator;
    private readonly NestedObjectTypeResolver $nestedObjectTypeResolver;
    private readonly ?RuleSetCompiler $rulesCompiler;
    private readonly RuleValueProcessor $ruleValues;
    private readonly ScalarValues $scalarValues;

    /**
     * $casts сохранён для совместимости; гидратация использует casts профиля DTO и свойства.
     */
    public function __construct(
        private readonly CastRegistry $casts,
        private readonly ?AttributeMetadataCache $cache = null,
        private readonly ?DtoHydrationProfileResolver $profileResolver = null,
        private readonly ?HydrationRules $rules = null,
    ) {
        $this->rulesCompiler = $rules === null ? null : new RuleSetCompiler($rules);
        $this->scalarValues = new ScalarValues();
        $this->ruleValues = new RuleValueProcessor($this->scalarValues);
        $this->namingStrategyResolver = new NamingStrategyResolver();
        $this->valueValidator = new HydrationValueValidator();
        $this->nestedObjectTypeResolver = new NestedObjectTypeResolver();
        $this->builtinHydrationCaster = new BuiltinHydrationCaster(
            typeSelector: new HydrationTypeSelector(),
            dtoHydrator: fn (mixed $nestedValue, string $dtoClass, ?PipelineContext $nestedContext): object => $this->hydrate($nestedValue, $dtoClass, $nestedContext),
        );
    }

    /**
     * Синглтон для DTO::from() без явного контекста.
     * Переданный глобальный registry не участвует в гидратации DTO.
     */
    public static function default(): self
    {
        return self::$default ??= new self(
            CastRegistry::global(),
        );
    }

    public static function forRules(HydrationRules $rules): self
    {
        return new self(new CastRegistry(), new AttributeMetadataCache(), rules: $rules);
    }

    /**
     * Гидрировать данные в DTO
     */
    public function hydrate(
        array|object $data,
        string $dtoClass,
        ?PipelineContext $context = null,
    ): object {
        return $this->scope($context)->hydrateDto($data, $dtoClass);
    }

    private function scope(?PipelineContext $context): HydrationScope
    {
        return HydrationScope::bind(
            fn (array|object $data, string $class, HydrationScope $scope): object => $this->hydrateNode($data, $class, $scope),
            $context,
            $this->rules !== null,
        );
    }

    private function hydrateNode(array|object $data, string $dtoClass, HydrationScope $scope): object
    {
        return $scope->node($data, function () use ($data, $dtoClass, $scope): object {
            $operation = fn (): object => $this->hydrateObject($data, $dtoClass, $scope);
            $boundary = is_subclass_of($dtoClass, ResponseDtoInterface::class)
                || is_object($data) && (method_exists($data, 'toArray') || $data instanceof JsonSerializable);
            return $boundary ? $scope->boundary($operation) : $operation();
        });
    }

    private function hydrateObject(array|object $data, string $dtoClass, HydrationScope $scope): object
    {
        $context = $scope->context();
        if (!class_exists($dtoClass) || !(new ReflectionClass($dtoClass))->isInstantiable()) {
            throw new ConfigurationException('Недоступен класс DTO для гидратации: ' . $dtoClass);
        }
        $array = $this->normalizeData($data);

        if (is_subclass_of($dtoClass, ResponseDtoInterface::class)) {
            $array = $dtoClass::computed($array, $context);
        }

        $metadata = $this->getHydrationMetadata($dtoClass);
        $constructor = $metadata['constructor'];
        $constructorParameters = [];
        foreach ($constructor ?? [] as $parameter) {
            $constructorParameters[$parameter['name']] = $parameter['reflection'];
        }
        $compiled = $this->rulesCompiler?->forClass($dtoClass);
        $resolvedHydration = $this->resolveHydration($dtoClass);
        $values = [];
        $origins = [];
        $consumed = new SourceConsumption();
        $receiver = $compiled?->declaration?->receiver;

        foreach ($metadata['properties'] as $propertyMeta) {
            $name = $propertyMeta['name'];
            if ($name === $receiver) {
                continue;
            }
            $field = $this->hydrateProperty(
                $array,
                $propertyMeta,
                $constructorParameters[$name] ?? null,
                $resolvedHydration,
                $compiled,
                $scope,
            );
            $origins[$name] = $field->location;
            $consumed->mergeAt($field->segments, $field->consumed);
            if ($field->state !== ValueState::Missing) {
                $values[$name] = $field->value;
            }
        }
        if ($receiver !== null) {
            [$keep, $rest] = $consumed->remainder($array);
            $values[$receiver] = $keep ? $rest : [];
        }

        try {
            return $this->instantiateHydratedDto($dtoClass, $metadata, $values);
        } catch (HydrationException $exception) {
            $location = $origins[$exception->path ?? '']
                ?? $scope->location()->descend([$exception->path ?? ''], kind: SourcePathKind::Expected);
            return $scope->at($location, static function () use ($exception): never {
                throw $exception;
            });
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $values
     */
    private function instantiateHydratedDto(string $dtoClass, array $metadata, array $values): object
    {
        $constructor = $metadata['constructor'];
        $reflection = new ReflectionClass($dtoClass);
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
     * @param array<string, mixed> $source
     * @param array<string, mixed> $meta
     */
    private function hydrateProperty(
        array $source,
        array $meta,
        ?ReflectionParameter $parameter,
        ResolvedDtoHydration $hydration,
        ?CompiledDtoRules $compiled,
        HydrationScope $scope,
    ): HydratedProperty {
        $name = $meta['name'];
        $property = $meta['property'];
        $rule = $compiled?->field($name);
        $policy = $compiled?->policyFor($name);
        if ($compiled !== null && !$compiled->legacyProfile) {
            $hydration = new ResolvedDtoHydration(new DtoHydrationPolicy(
                namingStrategy: $policy->naming ?? NamingStrategy::None,
                dateTime: $policy->dateTime,
                emptyStringBehavior: $policy->emptyString ?? EmptyStringBehavior::Keep,
            ), $hydration->casts);
        }
        $key = $meta['from']->name ?? $meta['map']->name
            ?? $this->namingStrategyResolver->resolveByStrategy($name, $hydration->policy->namingStrategy);
        $primary = $rule->from ?? $meta['nested']->from ?? $key;
        $fallbacks = $rule?->from !== null ? $rule->fallback : ($meta['nested']->fallback ?? []);
        if ($rule?->from === null && $fallbacks === [] && $meta['from'] !== null) {
            $fallbacks = $meta['from']->fallback;
        }
        [$resolved, $path] = $this->resolveValueWithFallbacks($source, $primary, $fallbacks);
        $segments = explode('.', $path);
        $location = $scope->location()->descend(
            $segments,
            kind: $resolved->isMissing() ? SourcePathKind::Expected : SourcePathKind::Resolved,
        );
        if ($location->kind === SourcePathKind::Expected) {
            $candidates = array_map(
                fn (string $candidate): SourceLocation => $scope->location()->descend(explode('.', $candidate)),
                [$primary, ...$fallbacks],
            );
            $location = new SourceLocation(
                $location->segments,
                $location->safeSegments,
                $location->kind,
                array_map(static fn (SourceLocation $item): string => SourceLocation::pointer($item->segments), $candidates),
                array_map(static fn (SourceLocation $item): string => SourceLocation::pointer($item->safeSegments), $candidates),
            );
        }

        try {
            return $scope->at($location, function () use (
                $source,
                $meta,
                $parameter,
                $hydration,
                $policy,
                $scope,
                $rule,
                $resolved,
                $segments,
                $location,
                $property,
            ): HydratedProperty {
                $state = $resolved->state;
                $value = $resolved->value;
                $consumed = $resolved->isMissing() ? new SourceConsumption() : SourceConsumption::all();
                if ($rule?->required && $state === ValueState::Missing) {
                    throw HydrationException::invalidValue('required_field_missing', (string) ($property->getType() ?? 'mixed'), 'missing');
                }
                if ($rule?->forbidExplicitNull && $state === ValueState::Null) {
                    throw HydrationException::invalidValue('explicit_null_not_allowed', 'non-null input', 'null');
                }
                if ($state === ValueState::Present) {
                    $input = $rule?->inputShape;
                    $shape = $rule?->cast === null ? $rule?->shape : null;
                    while ($shape?->kind === 'nullable') {
                        $shape = $shape->item;
                    }
                    $input ??= match ($shape?->kind) {
                        'dto' => InputShape::Object,
                        'list' => InputShape::List,
                        default => null,
                    };
                    if ($input !== null) {
                        $this->ruleValues->assertInput($value, $input, $shape->normalizeKeys ?? false);
                    }
                }
                if ($rule?->cast === null) {
                    [$state, $value] = $this->normalizeEmptyStringValue(
                        $state,
                        $value,
                        $meta['cast'],
                        $meta['emptyStringAsNull'],
                        $hydration,
                        $property,
                        $property->getDeclaringClass()->getName(),
                    );
                }
                $defaultApplied = false;
                $externalDefault = $rule?->default;
                if ($externalDefault !== null && in_array($state, $externalDefault->when, true)) {
                    $spec = $externalDefault->provider;
                    $value = $spec === null ? $externalDefault->value : $scope->provide(
                        new $spec->class(...$spec->args),
                        $value,
                        $state,
                        $source,
                    );
                    $defaultApplied = true;
                } elseif ($meta['default'] !== null && $this->shouldApplyDefault($meta['default'], $state)) {
                    $value = $this->resolveDefaultValue($meta['default'], $value, $state, $source, $scope->context(), $scope);
                    $defaultApplied = true;
                }
                if ($defaultApplied) {
                    $state = $value === null ? ValueState::Null : ValueState::Present;
                    $location = $location->boundary();
                }
                if ($state === ValueState::Missing && $this->shouldAutoDefaultEmptyTypedCollection($property, $meta['default'], $state)) {
                    $value = $this->wrapCollection([], $this->getPrimaryType($property));
                    return new HydratedProperty($value, ValueState::Present, $segments, $location, $consumed);
                }
                if ($state === ValueState::Missing) {
                    return new HydratedProperty(null, $state, $segments, $location, $consumed);
                }
                return $scope->at($location, function () use (
                    $value,
                    $state,
                    $segments,
                    $location,
                    $consumed,
                    $rule,
                    $policy,
                    $scope,
                    $meta,
                    $hydration,
                    $parameter,
                    $property,
                    $defaultApplied,
                    $resolved,
                ): HydratedProperty {
                    if ($rule?->cast !== null) {
                        $spec = $rule->cast;
                        $value = $scope->cast(new $spec->class(...$spec->args), $value);
                        $location = $location->boundary();
                        if ($rule->shape !== null) {
                            $value = $scope->at($location, fn (): mixed => $this->ruleValues->transform(
                                $value,
                                $rule->shape,
                                $policy,
                                $scope,
                                resultOnly: true,
                            )->value);
                        }
                    } elseif ($rule?->shape !== null && $value !== null) {
                        $shaped = $this->ruleValues->transform($value, $rule->shape, $policy, $scope, readyDto: $defaultApplied);
                        $value = $shaped->value;
                        if (!$defaultApplied && !$resolved->isMissing()) {
                            $consumed = $shaped->consumed;
                        }
                        $containerShape = $rule->shape;
                        while ($containerShape->kind === 'nullable') {
                            $containerShape = $containerShape->item;
                        }
                        if (is_array($value) && $containerShape->kind === 'list') {
                            $value = $this->wrapCollection($value, $this->getPrimaryType($property));
                        }
                    } elseif (!$rule?->noTransform) {
                        if ($meta['nested'] !== null) {
                            $nested = $meta['nested'];
                            if (
                                $this->rulesCompiler !== null && is_array($value)
                                && $this->nestedObjectTypeResolver->resolve($nested, $property) === null
                            ) {
                                $propertyType = $this->getPrimaryType($property);
                                $target = $nested->type ?? $propertyType;
                                $processed = (new NestedValueProcessor($this->ruleValues))->process($value, $nested, $target, $scope);
                                $value = $processed->value;
                                if ($this->isDiscriminatedNested($nested) || ($target !== null && class_exists($target))) {
                                    $value = $this->wrapCollection($value, $propertyType);
                                }
                                if (!$defaultApplied && !$resolved->isMissing()) {
                                    $consumed = $processed->consumed;
                                }
                            } else {
                                $value = $this->hydrateNested($value, $nested, $property, $scope->context(), $scope);
                            }
                        } else {
                            $customCast = $this->builtinHydrationCaster->usesCustomCast(
                                $value,
                                $meta['cast'],
                                $property,
                                $hydration,
                                $policy,
                            );
                            $value = $this->applyCasts(
                                $value,
                                $meta['cast'],
                                $meta['dateTimeFrom'],
                                $property,
                                $hydration,
                                $scope->context(),
                                $scope,
                                $policy,
                            );
                            if ($customCast) {
                                $location = $location->boundary();
                            }
                        }
                    }
                    $value = $scope->at($location, function () use ($value, $policy, $parameter, $property): mixed {
                        if ($policy?->scalars === ScalarPolicy::Strict) {
                            return $this->scalarValues->strictReflection(
                                $value,
                                $parameter === null ? $property->getType() : $parameter->getType(),
                                $parameter?->getDeclaringClass() ?? $property->getDeclaringClass(),
                            );
                        }
                        if ($parameter === null) {
                            $this->valueValidator->assertValue($value, $property->getType(), $property->getDeclaringClass(), '');
                        }
                        return $value;
                    });
                    return new HydratedProperty($value, $state, $segments, $location, $consumed);
                });
            });
        } catch (HydrationException $exception) {
            throw $exception->prependPath($name);
        }
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
        $scope = $this->scope($context);
        return $scope->node($items, function () use ($items, $dtoClass, $context, $scope): array {
            $result = [];
            foreach ($items as $key => $item) {
                $result[] = $scope->at(
                    $scope->location()->descend([$key], array_is_list($items)),
                    fn (): object => $this->hydrateItem($item, $dtoClass, $context, count($result), $scope),
                );
            }
            return $result;
        });
    }

    private function hydrateItem(
        mixed $item,
        string $dtoClass,
        ?PipelineContext $context,
        int $index,
        ?HydrationScope $scope = null,
    ): object {
        try {
            if (!is_array($item) && !is_object($item)) {
                throw HydrationException::invalidValue('unexpected_response_shape', $dtoClass, get_debug_type($item));
            }
            return $scope === null ? $this->hydrate($item, $dtoClass, $context) : $scope->hydrateDto($item, $dtoClass);
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
        ?HydrationScope $scope = null,
        ?RulePolicy $policy = null,
    ): mixed {
        return $this->builtinHydrationCaster->hydrate(
            value: $value,
            cast: $cast,
            dateTimeFrom: $dateTimeFrom,
            property: $property,
            resolved: $resolvedHydration,
            context: $context,
            scope: $scope,
            policy: $policy,
        );
    }

    private function resolveHydration(string $dtoClass): ResolvedDtoHydration
    {
        $resolver = $this->profileResolver ?? new DtoHydrationProfileResolver();

        return $resolver->resolveForDto($dtoClass);
    }

    /**
     * @param array<string, mixed> $values
     * @param array<int, array{name: string, reflection: ReflectionParameter, hasDefault: bool}> $constructor
     * @return array<string, mixed>
     */
    private function buildConstructorArguments(array $values, array $constructor): array
    {
        $args = [];

        foreach ($constructor as $parameter) {
            $paramName = $parameter['name'];

            if (array_key_exists($paramName, $values)) {
                $reflection = $parameter['reflection'];
                $this->valueValidator->assertValue(
                    $values[$paramName],
                    $reflection->getType(),
                    $reflection->getDeclaringClass(),
                    $paramName,
                );
                $args[$paramName] = $values[$paramName];
                continue;
            }

            if (!$parameter['hasDefault'] && !$parameter['reflection']->isVariadic()) {
                throw HydrationException::invalidValue(
                    'required_field_missing',
                    (string) ($parameter['reflection']->getType() ?? 'mixed'),
                    'missing',
                    $paramName,
                );
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
     * @param array<int, array{name: string, reflection: ReflectionParameter, hasDefault: bool}> $constructor
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
                throw HydrationException::invalidValue('required_field_missing', (string) ($property->getType() ?? 'mixed'), 'missing', $name);
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
            } catch (ReflectionException | \Error | \TypeError $exception) {
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
        ?HydrationScope $scope = null,
    ): mixed {
        if ($value === null) {
            return null;
        }

        $objectType = $this->nestedObjectTypeResolver->resolve($nested, $property);
        if ($objectType !== null) {
            if (
                (!is_array($value) && !is_object($value))
                || (is_array($value) && $value !== [] && array_is_list($value))
            ) {
                throw HydrationException::invalidValue(
                    'unexpected_response_shape',
                    $objectType,
                    get_debug_type($value),
                );
            }

            return $scope === null ? $this->hydrate($value, $objectType, $context) : $scope->hydrateDto($value, $objectType);
        }

        if ($nested->each !== null && is_array($value)) {
            $value = array_map(
                fn (mixed $item) => ArrayPath::getByPath($item, $nested->each),
                $value,
            );
        }

        if ($nested->itemCast !== null && is_array($value)) {
            $value = $this->applyNestedItemCast($value, $nested, $context, $scope);
        }

        $propertyType = $this->getPrimaryType($property);
        $targetType = $nested->type ?? $propertyType;

        if (is_array($value) && $this->isDiscriminatedNested($nested)) {
            return $this->hydrateDiscriminatedNested(
                value: $value,
                nested: $nested,
                propertyType: $propertyType,
                context: $context,
                scope: $scope,
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

                    $items[] = $this->hydrateItem($item, $targetType, $context, count($items), $scope);
                }

                return $this->wrapCollection($items, $propertyType);
            }

            return $value;
        }

        if ($targetType !== null && class_exists($targetType)) {
            if (!is_object($value)) {
                throw HydrationException::invalidValue('unexpected_response_shape', $targetType, get_debug_type($value));
            }
            return $scope === null ? $this->hydrate($value, $targetType, $context) : $scope->hydrateDto($value, $targetType);
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $items
     * @return array<int|string, mixed>
     */
    private function applyNestedItemCast(
        array $items,
        Nested $nested,
        ?PipelineContext $context,
        ?HydrationScope $scope = null,
    ): array {
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
                $items[$key] = $scope === null ? $cast->hydrate($item, $context) : $scope->cast($cast, $item);
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
        ?HydrationScope $scope = null,
    ): mixed {
        $items = [];
        $index = -1;

        foreach ($nested->map ?? [] as $class) {
            if (!is_string($class) || !class_exists($class) || !(new ReflectionClass($class))->isInstantiable()) {
                throw new ConfigurationException('Nested.map должен содержать доступные классы DTO');
            }
        }

        foreach ($value as $item) {
            $index++;
            [$discriminator, $payload, $rawItem] = $this->resolveDiscriminatorPayload($item, $nested);
            $class = $this->resolveDiscriminatorClass($nested, $discriminator);

            if ($class === null) {
                if ($nested->unknownVariant === NestedUnknownVariant::Skip) {
                    continue;
                }

                if ($nested->unknownVariant === NestedUnknownVariant::Error) {
                    throw HydrationException::invalidValue(
                        'unknown_nested_variant',
                        'variant: ' . implode('|', array_keys($nested->map ?? [])),
                        $discriminator === null ? 'missing' : 'string',
                        '[' . $index . ']',
                    );
                }

                $items[] = $rawItem;
                continue;
            }

            $items[] = $this->hydrateItem($payload, $class, $context, $index, $scope);
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
     *   constructor: ?array<int, array{name: string, reflection: ReflectionParameter, hasDefault: bool}>
     * }
     */
    private function getHydrationMetadata(string $dtoClass): array
    {
        $cacheKey = $dtoClass . ':hydrator';

        $cached = $this->cache?->get($cacheKey);
        if (is_array($cached)) {
            $cached['properties'] = $this->resolvePropertyAttributes(
                $cached['properties'],
                $cached['attributeFactories'],
                true,
            );
            return $cached;
        }

        $reflection = new ReflectionClass($dtoClass);
        $properties = [];
        $attributeFactories = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $factories = [];
            $attributes = $this->getPropertyAttributes($property, [
                'from' => From::class,
                'map' => Map::class,
                'nested' => Nested::class,
                'cast' => CastAttribute::class,
                'dateTimeFrom' => DateTimeFrom::class,
                'emptyStringAsNull' => EmptyStringAsNull::class,
                'default' => DefaultValue::class,
            ], $factories);
            $properties[] = [
                'name' => $property->getName(),
                'property' => $property,
            ] + $attributes;
            if ($factories !== []) {
                $attributeFactories[array_key_last($properties)] = $factories;
            }
        }

        $constructor = $reflection->getConstructor();
        $params = null;
        if ($constructor !== null) {
            $params = [];
            foreach ($constructor->getParameters() as $parameter) {
                $params[] = [
                    'name' => $parameter->getName(),
                    'reflection' => $parameter,
                    'hasDefault' => $parameter->isDefaultValueAvailable(),
                ];
            }
        }

        $metadata = [
            'properties' => $properties,
            'constructor' => $params,
            'attributeFactories' => $attributeFactories,
        ];

        if ($this->cache?->isEnabled()) {
            $template = $metadata;
            $template['properties'] = $this->resolvePropertyAttributes($properties, $attributeFactories, false);
            $this->cache->set($cacheKey, $template);
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $fallbacks
     * @return array{PathResult, string}
     */
    private function resolveValueWithFallbacks(array $data, string $primaryPath, array $fallbacks): array
    {
        $resolved = ArrayPath::getByPathWithStatus($data, $primaryPath);
        if (!$resolved->isMissing()) {
            return [$resolved, $primaryPath];
        }

        foreach ($fallbacks as $fallback) {
            $resolved = ArrayPath::getByPathWithStatus($data, $fallback);
            if (!$resolved->isMissing()) {
                return [$resolved, $fallback];
            }
        }

        return [$resolved, $primaryPath];
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
        ?HydrationScope $scope = null,
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
        return $scope === null
            ? $provider->resolve($value, $state, $source, $context)
            : $scope->provide($provider, $value, $state, $source);
    }
}
