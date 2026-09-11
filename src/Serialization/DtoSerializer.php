<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use Brahmic\ApiSutra\Attributes\AttributeMetadataCache;
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast as CastAttribute;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeTo;
use Brahmic\ApiSutra\Attributes\DataTransfer\Map;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Serialization\Concerns\ReflectionHelperTrait;
use Brahmic\ApiSutra\Serialization\VO\ResolvedDtoSerialization;
use Brahmic\ApiSutra\Support\ArrayPath;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use ReflectionClass;
use ReflectionProperty;

final class DtoSerializer
{
    use ReflectionHelperTrait;

    private static ?self $default = null;

    public function __construct(
        private readonly CastRegistry $casts,
        private readonly ?AttributeMetadataCache $cache = null,
        private readonly ?DtoSerializationProfileResolver $profileResolver = null,
    ) {
    }

    public static function default(): self
    {
        return self::$default ??= new self(
            CastRegistry::global(),
        );
    }

    /**
     * Сериализовать DTO в массив для body.
     *
     * @return array<string, mixed>
     */
    public function serialize(object $dto, ?PipelineContext $context = null): array
    {
        $resolved = $this->resolveSerialization($dto::class, $context?->config);

        return $this->serializeResolved(
            dto: $dto,
            resolved: $resolved,
            context: $context,
            nestedDtoSerializer: fn (object $nestedDto, ?PipelineContext $nestedContext): array => $this->serialize(
                $nestedDto,
                $nestedContext,
            ),
        );
    }

    /**
     * Сериализовать DTO по явно переданной policy.
     *
     * @return array<string, mixed>
     */
    public function serializeWithPolicy(
        object $dto,
        DtoSerializationPolicy $policy,
        ?PipelineContext $context = null,
        ?CastRegistry $casts = null,
    ): array {
        return $this->serializeResolved(
            dto: $dto,
            resolved: new ResolvedDtoSerialization(
                policy: $policy,
                casts: $casts ?? new CastRegistry(),
            ),
            context: $context,
            nestedDtoSerializer: fn (object $nestedDto, ?PipelineContext $nestedContext): array => $this->serializeWithPolicy(
                dto: $nestedDto,
                policy: $policy,
                context: $nestedContext,
                casts: $casts,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResolved(
        object $dto,
        ResolvedDtoSerialization $resolved,
        ?PipelineContext $context,
        callable $nestedDtoSerializer,
    ): array {
        $data = [];

        foreach ($this->getMetadata($dto) as $meta) {
            if ($meta['isStatic']) {
                continue;
            }

            $property = $meta['property'];
            $value = $this->readProperty($dto, $property);
            $to = $meta['to'];
            $map = $meta['map'];
            $cast = $meta['cast'];
            $dateTimeTo = $meta['dateTimeTo'];
            $valueResolver = new SerializationValueResolver($this->resolveCastRegistry($resolved));

            $name = $to?->name
                ?? $map?->name
                ?? $this->resolveName($meta['name'], $resolved->policy);
            $value = $valueResolver->resolve(
                value: $value,
                cast: $cast,
                dateTimeTo: $dateTimeTo,
                property: $property,
                context: $context,
                policy: $resolved->policy,
                dtoSerializer: $nestedDtoSerializer,
            );

            if ($value === null && !$resolved->policy->serializeNulls) {
                continue;
            }

            if (str_contains($name, '.')) {
                ArrayPath::setByPath($data, $name, $value);
                continue;
            }

            $data[$name] = $value;
        }

        return $data;
    }

    /**
     * Метаданные свойств DTO для сериализации.
     *
     * @return array<int, array{
     *   name: string,
     *   property: ReflectionProperty,
     *   to: ?To,
     *   map: ?Map,
     *   cast: ?CastAttribute,
     *   dateTimeTo: ?DateTimeTo,
     *   isStatic: bool
     * }>
     */
    private function getMetadata(object $dto): array
    {
        $class = $dto::class;
        $cacheKey = $class . ':dto-serializer';

        $cached = $this->cache?->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $reflection = new ReflectionClass($dto);
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                $properties[] = [
                    'name' => $property->getName(),
                    'property' => $property,
                    'to' => null,
                    'map' => null,
                    'cast' => null,
                    'dateTimeTo' => null,
                    'isStatic' => true,
                ];
                continue;
            }

            if (!$property->isPublic()) {
                throw new ConfigurationException(
                    'DTO serializer supports only public data properties: '
                    . $class . '::$' . $property->getName(),
                );
            }

            $properties[] = [
                'name' => $property->getName(),
                'property' => $property,
                'to' => $this->getAttribute($property, To::class),
                'map' => $this->getAttribute($property, Map::class),
                'cast' => $this->getAttribute($property, CastAttribute::class),
                'dateTimeTo' => $this->getAttribute($property, DateTimeTo::class),
                'isStatic' => $property->isStatic(),
            ];
        }

        $this->cache?->set($cacheKey, $properties);

        return $properties;
    }

    private function readProperty(object $dto, ReflectionProperty $property): mixed
    {
        if (!$property->isPublic()) {
            $property->setAccessible(true);
        }

        if (!$property->isInitialized($dto)) {
            // Неинициализированное свойство считаем null, чтобы избежать фатала
            return null;
        }

        return $property->getValue($dto);
    }

    private function resolveSerialization(string $dtoClass, ?ClientConfig $config): ResolvedDtoSerialization
    {
        $resolver = $this->profileResolver ?? new DtoSerializationProfileResolver();

        return $resolver->resolveForDto($dtoClass, $config);
    }

    private function resolveCastRegistry(ResolvedDtoSerialization $resolved): CastRegistry
    {
        return $resolved->casts;
    }

    private function resolveName(string $name, DtoSerializationPolicy $policy): string
    {
        return match ($policy->namingStrategy) {
            \Brahmic\ApiSutra\Enums\Configuration\NamingStrategy::SnakeCase => strtolower(
                preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name,
            ),
            \Brahmic\ApiSutra\Enums\Configuration\NamingStrategy::None => $name,
        };
    }
}
