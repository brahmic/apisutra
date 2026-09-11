<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrate;
use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrationProfile as DtoHydrationProfileAttribute;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\DtoHydrationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Serialization\VO\ResolvedDtoHydration;
use ReflectionClass;

final readonly class DtoHydrationProfileResolver
{
    public function resolveForDto(string $dtoClass): ResolvedDtoHydration
    {
        $profile = $this->resolveBoundProfile($dtoClass);
        $policy = $profile?->policy() ?? new DtoHydrationPolicy();
        $override = $this->resolveClassOverride($dtoClass);

        if ($override !== null) {
            $policy = $override->toPolicy($policy);
        }

        return new ResolvedDtoHydration(
            policy: $policy,
            casts: $this->buildCastRegistry($profile),
        );
    }

    private function resolveBoundProfile(string $dtoClass): ?DtoHydrationProfileInterface
    {
        foreach ($this->classHierarchy($dtoClass) as $class) {
            $attribute = ($class->getAttributes(DtoHydrationProfileAttribute::class)[0] ?? null)?->newInstance();

            if (!$attribute instanceof DtoHydrationProfileAttribute) {
                continue;
            }

            if (!class_exists($attribute->class)) {
                throw new ConfigurationException('DTO hydration profile class not found: ' . $attribute->class);
            }

            $profile = new $attribute->class();

            if (!$profile instanceof DtoHydrationProfileInterface) {
                throw new ConfigurationException(
                    'DTO hydration profile must implement DtoHydrationProfileInterface: ' . $attribute->class,
                );
            }

            return $profile;
        }

        return null;
    }

    private function resolveClassOverride(string $dtoClass): ?DtoHydrate
    {
        foreach ($this->classHierarchy($dtoClass) as $class) {
            $attribute = ($class->getAttributes(DtoHydrate::class)[0] ?? null)?->newInstance();

            if ($attribute instanceof DtoHydrate) {
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

    private function buildCastRegistry(?DtoHydrationProfileInterface $profile): CastRegistry
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

            throw new ConfigurationException('Invalid DTO hydration cast for type ' . $type);
        }

        return $registry;
    }
}
