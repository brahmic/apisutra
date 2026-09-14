<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast as CastAttribute;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use Brahmic\ApiSutra\Casts\DateTimeCast;
use Brahmic\ApiSutra\Casts\EnumCast;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Serialization\VO\ResolvedDtoHydration;
use Brahmic\ApiSutra\Serialization\Rules\HydrationScope;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
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
        ?HydrationScope $scope = null,
        ?RulePolicy $policy = null,
    ): mixed {
        if ($value === null) {
            return null;
        }

        if ($cast !== null) {
            if (!class_exists($cast->class) || !is_subclass_of($cast->class, CastInterface::class)) {
                throw new ConfigurationException('Неверный класс DTO cast: ' . $cast->class);
            }
            $castInstance = new $cast->class(...$cast->args);

            return $scope === null ? $castInstance->hydrate($value, $context) : $scope->cast($castInstance, $value);
        }

        $type = $this->typeSelector->resolveType($property, $value);
        if ($type === null) {
            return $value;
        }

        $registryCast = $resolved->casts->get($type);
        if ($registryCast !== null) {
            return $scope === null ? $registryCast->hydrate($value, $context) : $scope->cast($registryCast, $value);
        }
        $spec = $policy?->casts[$type] ?? null;
        if ($spec !== null) {
            $instance = new $spec->class(...$spec->args);
            return $scope === null ? $instance->hydrate($value, $context) : $scope->cast($instance, $value);
        }

        if ($type === 'int' && IntegerRange::overflows($value)) {
            throw HydrationException::invalidValue('integer_out_of_range', 'int', get_debug_type($value));
        }

        if ($policy?->scalars !== ScalarPolicy::Strict && $this->safeScalarHydrationCaster->canHydrate($type, $value)) {
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
            if (!is_array($value) && !is_object($value)) {
                throw HydrationException::invalidValue('unexpected_response_shape', $type, get_debug_type($value));
            }
            return $scope === null ? ($this->dtoHydrator)($value, $type, $context) : $scope->hydrateDto($value, $type);
        }

        if ($type === Base64File::class && is_string($value)) {
            return new Base64File($value);
        }

        return $value;
    }

    /** @internal Пользовательское преобразование отмечает границу происхождения результата. */
    public function usesCustomCast(
        mixed $value,
        ?CastAttribute $cast,
        ReflectionProperty $property,
        ResolvedDtoHydration $resolved,
        ?RulePolicy $policy,
    ): bool {
        if ($value === null) {
            return false;
        }
        $type = $this->typeSelector->resolveType($property, $value);
        return $cast !== null || $type !== null
            && ($resolved->casts->get($type) !== null || isset($policy?->casts[$type]));
    }
}
