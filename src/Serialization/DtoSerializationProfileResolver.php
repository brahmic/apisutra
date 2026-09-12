<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoSerializationProfile as DtoSerializationProfileAttribute;
use Brahmic\ApiSutra\Attributes\DataTransfer\DtoSerialize;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Serialization\VO\ResolvedDtoSerialization;
use ReflectionClass;

final readonly class DtoSerializationProfileResolver
{
    public function resolveForDto(string $dtoClass, ?ClientConfig $config = null): ResolvedDtoSerialization
    {
        $boundProfile = $this->resolveBoundProfile($dtoClass);
        $configProfile = $config?->dtoSerializationProfile;

        $profile = $this->resolveProfile($boundProfile, $configProfile, $dtoClass);
        $policy = $this->resolveBasePolicy($profile, $config);
        $override = $this->resolveClassOverride($dtoClass);
        if ($override !== null) {
            $policy = $override->toPolicy($policy);
        }

        return new ResolvedDtoSerialization(
            policy: $policy,
            casts: $this->buildCastRegistry($profile),
        );
    }

    public function resolveForBody(?ClientConfig $config = null): ResolvedDtoSerialization
    {
        $profile = $config?->dtoSerializationProfile;

        return new ResolvedDtoSerialization(
            policy: $this->resolveBasePolicy($profile, $config),
            casts: $this->buildCastRegistry($profile),
        );
    }

    public function resolveForWireDto(string $dtoClass, ?ClientConfig $config = null): ResolvedDtoSerialization
    {
        $boundProfile = $this->resolveBoundProfile($dtoClass);
        $configProfile = $config?->dtoSerializationProfile;
        $profile = $boundProfile ?? $configProfile;
        $dxPolicy = $this->resolveBasePolicy($profile, $config);

        return new ResolvedDtoSerialization(
            policy: $config->wireBodySerializationPolicy ?? $this->deriveWirePolicy($dxPolicy, $config),
            casts: $this->buildCastRegistry($profile),
        );
    }

    private function resolveProfile(
        ?DtoSerializationProfileInterface $boundProfile,
        ?DtoSerializationProfileInterface $configProfile,
        string $dtoClass,
    ): ?DtoSerializationProfileInterface {
        if ($boundProfile !== null && $configProfile !== null && $boundProfile::class !== $configProfile::class) {
            throw new ConfigurationException(
                'DTO profile conflict for ' . $dtoClass . ': '
                . $boundProfile::class . ' != ' . $configProfile::class,
            );
        }

        return $boundProfile ?? $configProfile;
    }

    private function resolveBasePolicy(
        ?DtoSerializationProfileInterface $profile,
        ?ClientConfig $config,
    ): DtoSerializationPolicy {
        if ($profile !== null) {
            return $profile->policy();
        }

        return new DtoSerializationPolicy();
    }

    private function deriveWirePolicy(DtoSerializationPolicy $dxPolicy, ?ClientConfig $config): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy(
            enumOutput: \Brahmic\ApiSutra\Enums\Serialization\EnumOutput::Value,
            strictEnums: false,
            namingStrategy: $dxPolicy->namingStrategy,
            serializeNulls: $dxPolicy->serializeNulls,
            dateTime: $config->requestDateTime ?? $dxPolicy->dateTime,
        );
    }

    private function resolveBoundProfile(string $dtoClass): ?DtoSerializationProfileInterface
    {
        foreach ($this->classHierarchy($dtoClass) as $class) {
            $attribute = ($class->getAttributes(DtoSerializationProfileAttribute::class)[0] ?? null)?->newInstance();
            if (!$attribute instanceof DtoSerializationProfileAttribute) {
                continue;
            }

            if (!class_exists($attribute->class)) {
                throw new ConfigurationException('DTO serialization profile class not found: ' . $attribute->class);
            }

            $profile = new $attribute->class();
            if (!$profile instanceof DtoSerializationProfileInterface) {
                throw new ConfigurationException(
                    'DTO serialization profile must implement DtoSerializationProfileInterface: ' . $attribute->class,
                );
            }

            return $profile;
        }

        return null;
    }

    private function resolveClassOverride(string $dtoClass): ?DtoSerialize
    {
        foreach ($this->classHierarchy($dtoClass) as $class) {
            $attribute = ($class->getAttributes(DtoSerialize::class)[0] ?? null)?->newInstance();
            if ($attribute instanceof DtoSerialize) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * @return array<int, ReflectionClass<object>>
     */
    private function classHierarchy(string $class): array
    {
        $hierarchy = [];
        $current = new ReflectionClass($class);

        do {
            $hierarchy[] = $current;
            $current = $current->getParentClass();
        } while ($current !== false);

        return $hierarchy;
    }

    private function buildCastRegistry(?DtoSerializationProfileInterface $profile): CastRegistry
    {
        $registry = new CastRegistry();
        if ($profile === null) {
            return $registry;
        }

        foreach ($profile->casts() as $type => $cast) {
            if ($cast instanceof CastInterface) {
                $registry->register($type, $cast);
                continue;
            }

            if (is_string($cast) && class_exists($cast) && is_subclass_of($cast, CastInterface::class)) {
                /** @var class-string<CastInterface> $cast */
                $registry->register($type, new $cast());
                continue;
            }

            throw new ConfigurationException('Invalid DTO serialization cast for type ' . $type);
        }

        return $registry;
    }
}
