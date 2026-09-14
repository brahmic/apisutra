<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use Brahmic\ApiSutra\Attributes\AttributeMetadataCache;
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast as CastAttribute;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Request\Ignore;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Request\RequestDefaults;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\DateTimeSerializationPolicy;
use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Http\FileFormat;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Enums\Request\RequestUnmappedTarget;
use Brahmic\ApiSutra\Enums\Serialization\EnumOutput;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Serialization\Concerns\ReflectionHelperTrait;
use Brahmic\ApiSutra\Serialization\EnumSerializationHelper;
use Brahmic\ApiSutra\Serialization\VO\PropertyMeta;
use Brahmic\ApiSutra\Serialization\VO\ResolvedDtoSerialization;
use Brahmic\ApiSutra\Serialization\VO\RequestPartsBag;
use Brahmic\ApiSutra\Support\ArrayPath;
use Brahmic\ApiSutra\VO\Files\FileInput;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Closure;
use ReflectionClass;
use ReflectionProperty;
use UnitEnum;

final readonly class RequestPartsCollector
{
    use ReflectionHelperTrait;

    private NamingStrategyResolver $namingStrategyResolver;
    private EnumSerializationHelper $enumSerializer;

    /**
     * @param Closure(object, ?PipelineContext): array $dtoSerializer
     */
    public function __construct(
        private CastRegistry $casts,
        private ?AttributeMetadataCache $cache,
        private Closure $dtoSerializer,
    ) {
        $this->namingStrategyResolver = new NamingStrategyResolver();
        $this->enumSerializer = new EnumSerializationHelper();
    }

    /**
     * @param array<string, mixed> $paginationOverrides
     * @param array<string, mixed> $placeholders
     */
    public function collect(
        RequestInterface $request,
        ?PipelineContext $context,
        array $paginationOverrides,
        array $placeholders,
        HttpMethod $method,
    ): RequestPartsBag {
        $properties = $this->getPropertyMetadata($request);
        $query = [];
        $body = [];
        $bodyIsRoot = false;
        $rootBody = null;
        $rootBodyName = null;
        $headers = [];
        $files = [];
        $fileFormat = null;
        $bodySerialization = new ResolvedDtoSerialization(
            policy: $this->resolveWireBodyPolicy($context),
            casts: new CastRegistry(),
        );
        $requestPartsOutput = $this->resolveRequestPartsEnumOutput($context);
        $strictMode = $this->resolveRequestPartsStrictEnums($context);
        $unmappedTarget = $this->resolveUnmappedTarget($request, $method);
        $rootMetadata = $this->resolveBodyRootMetadata($properties);

        if ($rootMetadata !== null) {
            $bodyIsRoot = true;
            $rootBodyName = $rootMetadata->name;
        }

        foreach ($properties as $metadata) {
            if ($metadata->shouldSkip()) {
                continue;
            }

            $name = $metadata->name;
            $value = $paginationOverrides[$name] ?? $request->{$name};
            $pathAttr = $metadata->path;
            $queryAttr = $metadata->query;
            $bodyAttr = $metadata->body;
            $bodyRootAttr = $metadata->bodyRoot;
            $headerAttr = $metadata->header;
            $fileAttr = $metadata->file;
            $castAttr = $metadata->cast;
            $property = $metadata->property;

            if ($bodyRootAttr !== null) {
                if ($rootBodyName !== $name) {
                    continue;
                }

                if ($pathAttr !== null || array_key_exists($name, $placeholders)) {
                    throw new ConfigurationException(
                        'BodyRoot не может использоваться вместе с Path/placeholder: ' . $name,
                    );
                }

                $rootBody = $this->serializeValue(
                    $value,
                    $castAttr,
                    $property,
                    $context,
                    $bodySerialization,
                );

                continue;
            }

            if ($fileAttr !== null) {
                if ($bodyIsRoot) {
                    throw new ConfigurationException(
                        'BodyRoot не может использоваться вместе с File: ' . $name,
                    );
                }

                $fileFormat = $fileAttr->format;
                $fileName = $fileAttr->name ?? $name;
                $this->collectFiles($files, $fileName, $value);
                continue;
            }

            if ($headerAttr !== null) {
                if ($value !== null) {
                    $value = $this->serializeEnumOnly($value, $requestPartsOutput, $strictMode);
                    $headers[$headerAttr->name] = (string) $value;
                }
                continue;
            }

            $isPath = $pathAttr !== null || array_key_exists($name, $placeholders);
            if ($isPath) {
                $paramName = $pathAttr->name ?? $name;
                if ($value !== null) {
                    $value = $this->serializeEnumOnly($value, $requestPartsOutput, $strictMode);
                    $placeholders[$paramName] = $value;
                } else {
                    $placeholders[$paramName] = null;
                }
                continue;
            }

            $targetName = $queryAttr->name ?? $this->namingStrategyResolver->resolve($name, $context);

            if ($bodyAttr !== null) {
                if ($bodyIsRoot) {
                    throw new ConfigurationException(
                        'BodyRoot не может использоваться вместе с Body: ' . $name,
                    );
                }

                $value = $this->serializeValue(
                    $value,
                    $castAttr,
                    $property,
                    $context,
                    $bodySerialization,
                );
                if ($value === null && !$bodySerialization->policy->serializeNulls) {
                    continue;
                }

                if ($bodyAttr->nested !== null) {
                    $path = $bodyAttr->nested !== '' ? $bodyAttr->nested : $targetName;
                    ArrayPath::setByPath($body, $path, $value);
                    continue;
                }

                $body[$targetName] = $value;
                continue;
            }

            $serializeToQuery = $queryAttr !== null
                || $this->shouldSerializeToQuery($queryAttr, $bodyAttr, $unmappedTarget);

            if ($serializeToQuery) {
                $value = $this->serializeRequestPartValue(
                    $value,
                    $castAttr,
                    $property,
                    $context,
                    $requestPartsOutput,
                    $strictMode,
                );
                if ($value === null && !$this->shouldIncludeNull($queryAttr, $context)) {
                    continue;
                }

                $query[$targetName] = [
                    'value' => $value,
                    'format' => $queryAttr?->arrayFormat,
                ];
                continue;
            }

            if ($bodyIsRoot) {
                throw new ConfigurationException(
                    'BodyRoot конфликтует с полем, которое попадает в body по умолчанию: ' . $name,
                );
            }

            $value = $this->serializeValue(
                $value,
                $castAttr,
                $property,
                $context,
                $bodySerialization,
            );
            if ($value === null && !$bodySerialization->policy->serializeNulls) {
                continue;
            }
            $body[$targetName] = $value;
        }

        return new RequestPartsBag(
            query: $query,
            body: $bodyIsRoot ? $rootBody : $body,
            bodyIsRoot: $bodyIsRoot,
            headers: $headers,
            files: $files,
            fileFormat: $fileFormat,
            placeholders: $placeholders,
        );
    }

    private function shouldSerializeToQuery(
        ?Query $queryAttr,
        ?Body $bodyAttr,
        RequestUnmappedTarget $unmappedTarget,
    ): bool {
        if ($queryAttr !== null) {
            return true;
        }

        if ($bodyAttr !== null) {
            return false;
        }

        return $unmappedTarget === RequestUnmappedTarget::Query;
    }

    /**
     * @param array<int, array{name: string, file: FileInput}> $files
     */
    private function collectFiles(array &$files, string $name, mixed $value): void
    {
        if ($value instanceof FileInput) {
            $files[] = ['name' => $name, 'file' => $value];
            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($item instanceof FileInput) {
                    $files[] = ['name' => $name, 'file' => $item];
                }
            }
        }
    }

    /**
     * @return array<int, PropertyMeta>
     */
    private function getPropertyMetadata(object $request): array
    {
        $class = $request::class;
        $cacheKey = $class . ':serializer';

        $cached = $this->cache?->get($cacheKey);
        if (is_array($cached)) {
            $properties = $cached['properties'];
            foreach ($cached['attributeFactories'] as $index => $factories) {
                $values = $this->resolvePropertyAttributes([(array) $properties[$index]], [$factories], true);
                $properties[$index] = new PropertyMeta(...$values[0]);
            }
            return $properties;
        }

        $reflection = new ReflectionClass($request);
        $properties = [];
        $attributeFactories = [];

        foreach ($reflection->getProperties() as $property) {
            $factories = [];
            $attributes = $this->getPropertyAttributes($property, [
                'ignore' => Ignore::class,
                'path' => Path::class,
                'query' => Query::class,
                'body' => Body::class,
                'bodyRoot' => BodyRoot::class,
                'header' => Header::class,
                'file' => File::class,
                'cast' => CastAttribute::class,
            ], $factories);
            $properties[] = new PropertyMeta(
                $property->getName(),
                $property,
                $property->isPublic(),
                $property->isStatic(),
                ...$attributes,
            );
            if ($factories !== []) {
                $attributeFactories[array_key_last($properties)] = $factories;
            }
        }

        if ($this->cache?->isEnabled()) {
            $templates = $properties;
            foreach ($attributeFactories as $index => $factories) {
                $values = $this->resolvePropertyAttributes([(array) $properties[$index]], [$factories], false);
                $templates[$index] = new PropertyMeta(...$values[0]);
            }
            $this->cache->set($cacheKey, [
                'properties' => $templates,
                'attributeFactories' => $attributeFactories,
            ]);
        }

        return $properties;
    }

    /**
     * @param array<int, PropertyMeta> $properties
     */
    private function resolveBodyRootMetadata(array $properties): ?PropertyMeta
    {
        $roots = [];
        foreach ($properties as $metadata) {
            if ($metadata->bodyRoot === null) {
                continue;
            }

            $this->assertBodyRootMetadataIsValid($metadata);
            $roots[] = $metadata;
        }

        if (count($roots) > 1) {
            throw new ConfigurationException('Найдено более одного BodyRoot в запросе');
        }

        return $roots[0] ?? null;
    }

    private function assertBodyRootMetadataIsValid(PropertyMeta $metadata): void
    {
        if (!$metadata->isPublic || $metadata->isStatic) {
            throw new ConfigurationException('BodyRoot должен быть объявлен на публичном нестатическом свойстве');
        }

        if ($metadata->ignore !== null) {
            throw new ConfigurationException('BodyRoot не может использоваться вместе с Ignore: ' . $metadata->name);
        }

        if ($metadata->path !== null || $metadata->query !== null || $metadata->body !== null) {
            throw new ConfigurationException(
                'BodyRoot не может использоваться вместе с Path/Query/Body: ' . $metadata->name,
            );
        }

        if ($metadata->header !== null || $metadata->file !== null) {
            throw new ConfigurationException(
                'BodyRoot не может использоваться вместе с Header/File: ' . $metadata->name,
            );
        }
    }

    private function resolveUnmappedTarget(RequestInterface $request, HttpMethod $method): RequestUnmappedTarget
    {
        $defaults = $this->getRequestDefaults($request);
        $target = $defaults->unmapped ?? RequestUnmappedTarget::Convention;

        if ($target !== RequestUnmappedTarget::Convention) {
            return $target;
        }

        return $method->isQueryMethod()
            ? RequestUnmappedTarget::Query
            : RequestUnmappedTarget::Body;
    }

    private function getRequestDefaults(RequestInterface $request): ?RequestDefaults
    {
        $class = $request::class;
        $cacheKey = $class . ':serializer:request-defaults';

        $cached = $this->cache?->get($cacheKey);
        if (is_array($cached) && array_key_exists('unmapped', $cached)) {
            $unmapped = $cached['unmapped'] ?? null;
            if (!is_string($unmapped)) {
                return null;
            }

            return new RequestDefaults(RequestUnmappedTarget::from($unmapped));
        }

        $reflection = new ReflectionClass($request);
        $defaults = ($reflection->getAttributes(RequestDefaults::class)[0] ?? null)?->newInstance();
        $this->cache?->set($cacheKey, [
            'unmapped' => $defaults?->unmapped->value,
        ]);

        return $defaults;
    }

    private function serializeValue(
        mixed $value,
        ?CastAttribute $cast,
        ReflectionProperty $property,
        ?PipelineContext $context,
        ResolvedDtoSerialization $resolved,
    ): mixed {
        $resolver = new SerializationValueResolver(
            $resolved->casts->isEmpty() ? $this->casts : $resolved->casts,
        );

        return $resolver->resolve(
            value: $value,
            cast: $cast,
            dateTimeTo: null,
            property: $property,
            context: $context,
            policy: $resolved->policy,
            dtoSerializer: $this->dtoSerializer,
        );
    }

    private function serializeRequestPartValue(
        mixed $value,
        ?CastAttribute $cast,
        ReflectionProperty $property,
        ?PipelineContext $context,
        EnumOutput $enumOutput,
        bool $strictMode,
    ): mixed {
        $resolver = new SerializationValueResolver($this->casts);
        $policy = $this->buildRequestPartsPolicy($context, $enumOutput, $strictMode);

        return $resolver->resolve(
            value: $value,
            cast: $cast,
            dateTimeTo: null,
            property: $property,
            context: $context,
            policy: $policy,
            dtoSerializer: $this->dtoSerializer,
        );
    }

    private function buildRequestPartsPolicy(
        ?PipelineContext $context,
        EnumOutput $enumOutput,
        bool $strictMode,
    ): DtoSerializationPolicy {
        return new DtoSerializationPolicy(
            enumOutput: $enumOutput,
            strictEnums: $strictMode,
            dateTime: new DateTimeSerializationPolicy(
                format: $context?->config->requestDateTime->format ?? DATE_ATOM,
                timezone: $context?->config->requestDateTime->timezone,
            ),
        );
    }

    private function resolveWireBodyPolicy(?PipelineContext $context): DtoSerializationPolicy
    {
        $explicit = $context?->config->wireBodySerializationPolicy;
        if ($explicit instanceof DtoSerializationPolicy) {
            return $explicit;
        }

        return new DtoSerializationPolicy(
            enumOutput: EnumOutput::Value,
            strictEnums: false,
            serializeNulls: false,
            dateTime: $context?->config->requestDateTime ?? new DateTimeSerializationPolicy(),
        );
    }

    private function shouldIncludeNull(?Query $queryAttr, ?PipelineContext $context): bool
    {
        if ($queryAttr?->nullable !== null) {
            return $queryAttr->nullable;
        }

        return $context?->config->serializeNulls ?? false;
    }

    private function resolveRequestPartsEnumOutput(?PipelineContext $context): EnumOutput
    {
        return $context?->config->requestPartsEnumOutput ?? EnumOutput::Value;
    }

    private function resolveRequestPartsStrictEnums(?PipelineContext $context): bool
    {
        return $context?->config->requestPartsStrictEnums ?? false;
    }

    private function serializeEnumOnly(mixed $value, EnumOutput $output, bool $strictMode): mixed
    {
        if ($value instanceof UnitEnum) {
            return $this->enumSerializer->serializeEnum($value, $output, $strictMode);
        }

        return $value;
    }
}
