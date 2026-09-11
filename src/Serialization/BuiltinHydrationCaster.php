<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast as CastAttribute;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use Brahmic\ApiSutra\Casts\DateTimeCast;
use Brahmic\ApiSutra\Casts\EnumCast;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use Brahmic\ApiSutra\Serialization\VO\ResolvedDtoHydration;
use Brahmic\ApiSutra\VO\Files\Base64File;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Closure;
use DateTimeInterface;
use ReflectionProperty;
use UnitEnum;

final readonly class BuiltinHydrationCaster
{
    private Closure $dtoHydrator;
    private SafeScalarHydrationCaster $safeScalarHydrationCaster;

    /**
     * @param callable(mixed, string, ?PipelineContext): object $dtoHydrator
     */
    public function __construct(
        private HydrationTypeSelector $typeSelector,
        callable $dtoHydrator,
        ?SafeScalarHydrationCaster $safeScalarHydrationCaster = null,
    ) {
        $this->dtoHydrator = $dtoHydrator instanceof Closure
            ? $dtoHydrator
            : Closure::fromCallable($dtoHydrator);
        $this->safeScalarHydrationCaster = $safeScalarHydrationCaster ?? new SafeScalarHydrationCaster();
    }

    public function hydrate(
        mixed $value,
        ?CastAttribute $cast,
        ?DateTimeFrom $dateTimeFrom,
        ReflectionProperty $property,
        ResolvedDtoHydration $resolved,
        ?PipelineContext $context,
    ): mixed {
        if ($value === null) {
            return null;
        }

        if ($cast !== null) {
            $castInstance = new $cast->class(...$cast->args);

            return $castInstance->hydrate($value, $context);
        }

        $type = $this->typeSelector->resolveType($property, $value);
        if ($type === null) {
            return $value;
        }

        $registryCast = $resolved->casts->get($type);
        if ($registryCast !== null) {
            return $registryCast->hydrate($value, $context);
        }

        if ($this->safeScalarHydrationCaster->canHydrate($type, $value)) {
            return $this->safeScalarHydrationCaster->hydrate($type, $value);
        }

        if (is_subclass_of($type, DateTimeInterface::class)) {
            $policy = $dateTimeFrom?->toPolicy($resolved->policy->dateTime) ?? $resolved->policy->dateTime;

            return DateTimeCast::fromHydrationPolicy($policy)->hydrate($value, $context);
        }

        if (enum_exists($type) || is_subclass_of($type, UnitEnum::class)) {
            return (new EnumCast($type))->hydrate($value, $context);
        }

        if (is_subclass_of($type, DtoInterface::class)) {
            return ($this->dtoHydrator)($value, $type, $context);
        }

        if ($type === Base64File::class && is_string($value)) {
            return new Base64File($value);
        }

        return $value;
    }
}
