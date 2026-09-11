<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes;

use Brahmic\ApiSutra\Contracts\Interfaces\Attributes\AttributeContextHandlerInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Attributes\AttributeHandlerInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use Brahmic\ApiSutra\Enums\Attributes\AttributeContextType;
use Brahmic\ApiSutra\Enums\Pipeline\PipelineStage;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Closure;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

final class AttributeRegistry
{
    /**
     * @var array<string, string|AttributeHandlerInterface|AttributeContextHandlerInterface>
     */
    private array $handlers = [];

    private readonly ?Closure $resolver;

    /**
     * @param callable(string):object|null $resolver
     */
    public function __construct(
        ?callable $resolver = null,
        private readonly ?AttributeMetadataCache $cache = null,
    ) {
        $this->resolver = $resolver !== null ? Closure::fromCallable($resolver) : null;
    }

    public function register(string $attributeClass, string $handlerClass): void
    {
        $this->handlers[$attributeClass] = $handlerClass;
    }

    public function getHandler(string $attributeClass): AttributeHandlerInterface|AttributeContextHandlerInterface|null
    {
        $handler = $this->handlers[$attributeClass] ?? null;
        if ($handler === null) {
            return null;
        }

        if ($handler instanceof AttributeHandlerInterface || $handler instanceof AttributeContextHandlerInterface) {
            return $handler;
        }

        $resolver = $this->resolver ?? fn (string $class) => new $class();
        $instance = $resolver($handler);

        if (!$instance instanceof AttributeHandlerInterface && !$instance instanceof AttributeContextHandlerInterface) {
            return null;
        }

        $this->handlers[$attributeClass] = $instance;

        return $instance;
    }

    public function process(object $target, PipelineContext $context): void
    {
        $this->processStage($target, $context, PipelineStage::Started);
    }

    public function processStage(
        object $target,
        PipelineContext $context,
        PipelineStage $stage,
        mixed $data = null,
    ): mixed {
        $reflection = new ReflectionClass($target);
        $metadata = $this->getMetadata($reflection);
        $classAttributes = $this->instantiateAttributes($metadata['class']);
        $currentData = $data;
        $type = $this->resolveContextType($target);

        foreach ($this->flattenAttributes($metadata, $reflection) as $item) {
            /** @var ReflectionAttribute $attribute */
            $attribute = $item['attribute'];
            /** @var ReflectionClass|ReflectionProperty $attributeTarget */
            $attributeTarget = $item['target'];

            $handler = $this->getHandler($attribute->getName());
            if ($handler === null) {
                continue;
            }

            $currentData = $this->handleAttribute(
                handler: $handler,
                attribute: $attribute,
                target: $attributeTarget,
                context: $context,
                stage: $stage,
                data: $currentData,
                type: $type,
                classAttributes: $classAttributes,
            );
        }

        return $currentData;
    }

    /**
     * Метаданные атрибутов с кэшированием.
     *
     * @return array{class: array<int, array{attribute: ReflectionAttribute}>, properties: array<int, array{attribute: ReflectionAttribute, property: ReflectionProperty}>}
     */
    private function getMetadata(ReflectionClass $reflection): array
    {
        $className = $reflection->getName();
        $metadata = $this->cache?->get($className);

        if ($metadata === null) {
            $metadata = $this->scan($reflection);
            $this->cache?->set($className, $metadata);
        }

        return $metadata;
    }

    /**
     * @return array{class: array<int, array{attribute: ReflectionAttribute}>, properties: array<int, array{attribute: ReflectionAttribute, property: ReflectionProperty}>}
     */
    private function scan(ReflectionClass $reflection): array
    {
        $classAttributes = array_map(
            static fn (ReflectionAttribute $attribute): array => ['attribute' => $attribute],
            $reflection->getAttributes(),
        );

        $propertyAttributes = [];
        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes() as $attribute) {
                $propertyAttributes[] = [
                    'attribute' => $attribute,
                    'property' => $property,
                ];
            }
        }

        return [
            'class' => $classAttributes,
            'properties' => $propertyAttributes,
        ];
    }

    /**
     * @param array<int, array{attribute: ReflectionAttribute}> $items
     * @return array<int, object>
     */
    private function instantiateAttributes(array $items): array
    {
        return array_map(
            static fn (array $item): object => $item['attribute']->newInstance(),
            $items,
        );
    }

    /**
     * @param array{class: array<int, array{attribute: ReflectionAttribute}>, properties: array<int, array{attribute: ReflectionAttribute, property: ReflectionProperty}>} $metadata
     * @return array<int, array{attribute: ReflectionAttribute, target: ReflectionClass|ReflectionProperty}>
     */
    private function flattenAttributes(array $metadata, ReflectionClass $reflection): array
    {
        $result = [];

        foreach ($metadata['class'] as $item) {
            $result[] = [
                'attribute' => $item['attribute'],
                'target' => $reflection,
            ];
        }

        foreach ($metadata['properties'] as $item) {
            $result[] = [
                'attribute' => $item['attribute'],
                'target' => $item['property'],
            ];
        }

        return $result;
    }

    private function resolveContextType(object $target): AttributeContextType
    {
        return match (true) {
            $target instanceof RequestInterface => AttributeContextType::Request,
            $target instanceof DtoInterface => AttributeContextType::Dto,
            default => AttributeContextType::Request,
        };
    }

    private function handleAttribute(
        AttributeHandlerInterface|AttributeContextHandlerInterface $handler,
        ReflectionAttribute $attribute,
        ReflectionProperty|ReflectionClass $target,
        PipelineContext $context,
        PipelineStage $stage,
        mixed $data,
        AttributeContextType $type,
        array $classAttributes,
    ): mixed {
        if ($handler instanceof AttributeContextHandlerInterface) {
            $result = $handler->handle(new AttributeContext(
                attribute: $attribute->newInstance(),
                target: $target,
                type: $type,
                stage: $stage,
                context: $context,
                data: $data,
                classAttributes: $classAttributes,
            ));

            return $result ?? $data;
        }

        $handler->handle($attribute->newInstance(), $target, $context);

        return $data;
    }
}
