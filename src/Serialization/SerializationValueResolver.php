<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast as CastAttribute;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeTo;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Casts\DateTimeCast;
use Brahmic\ApiSutra\Config\DateTimeSerializationPolicy;
use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use DateTimeInterface;
use JsonSerializable;
use ReflectionProperty;
use UnitEnum;

final readonly class SerializationValueResolver
{
    private PropertyTypeInspector $propertyTypeInspector;
    private EnumSerializationHelper $enumSerializer;

    public function __construct(
        private CastRegistry $casts,
        ?PropertyTypeInspector $propertyTypeInspector = null,
    ) {
        $this->propertyTypeInspector = $propertyTypeInspector ?? new PropertyTypeInspector();
        $this->enumSerializer = new EnumSerializationHelper();
    }

    /**
     * @param callable(object, ?PipelineContext): array<string, mixed> $dtoSerializer
     */
    public function resolve(
        mixed $value,
        ?CastAttribute $cast,
        ?DateTimeTo $dateTimeTo,
        ReflectionProperty $property,
        ?PipelineContext $context,
        DtoSerializationPolicy $policy,
        callable $dtoSerializer,
    ): mixed {
        if ($value === null) {
            return null;
        }

        if ($cast !== null) {
            return (new $cast->class(...$cast->args))->serialize($value, $context);
        }

        if ($value instanceof DtoInterface) {
            return $dtoSerializer($value, $context);
        }

        if (is_array($value)) {
            return $this->enumSerializer->serializeArray(
                items: $value,
                output: $policy->enumOutput,
                strictMode: $policy->strictEnums,
                dtoSerializer: fn (DtoInterface $dto): array => $dtoSerializer($dto, $context),
            );
        }

        $resolved = $this->resolveCastByType(
            $this->propertyTypeInspector->resolvePropertyTypeByValue($property, $value),
            $dateTimeTo?->toPolicy($policy->dateTime) ?? $policy->dateTime,
        );
        if ($resolved !== null) {
            return $resolved->serialize($value, $context);
        }

        if ($value instanceof UnitEnum) {
            return $this->enumSerializer->serializeEnum($value, $policy->enumOutput, $policy->strictEnums);
        }

        $resolved = $this->resolveCastByValue($value, $dateTimeTo?->toPolicy($policy->dateTime) ?? $policy->dateTime);
        if ($resolved !== null) {
            return $resolved->serialize($value, $context);
        }

        return $this->serializeObject($value);
    }

    private function resolveCastByType(?string $type, DateTimeSerializationPolicy $dateTimePolicy): ?CastInterface
    {
        if ($type === null) {
            return null;
        }

        return match (true) {
            ($cast = $this->casts->get($type)) !== null => $cast,
            is_subclass_of($type, DateTimeInterface::class) => DateTimeCast::fromSerializationPolicy($dateTimePolicy),
            default => null,
        };
    }

    private function resolveCastByValue(mixed $value, DateTimeSerializationPolicy $dateTimePolicy): ?CastInterface
    {
        return match (true) {
            $value instanceof DateTimeInterface => DateTimeCast::fromSerializationPolicy($dateTimePolicy),
            default => null,
        };
    }

    private function serializeObject(mixed $value): mixed
    {
        if (!is_object($value)) {
            return $value;
        }

        return match (true) {
            method_exists($value, 'toArray') => $value->toArray(),
            $value instanceof JsonSerializable => $value->jsonSerialize(),
            default => $value,
        };
    }
}
