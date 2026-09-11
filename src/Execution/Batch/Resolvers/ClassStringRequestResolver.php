<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution\Batch\Resolvers;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Execution\Batch\BatchContext;
use Brahmic\ApiSutra\Execution\Batch\RequestResolverInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use ReflectionClass;

/**
 * Резолвер запросов по имени класса.
 */
final class ClassStringRequestResolver implements RequestResolverInterface
{
    public function supports(mixed $item): bool
    {
        return is_string($item) && class_exists($item);
    }

    public function resolve(mixed $item, BatchContext $context): ?RequestInterface
    {
        return $this->instantiateFromParent($item, $context->parent);
    }

    /**
     * Создаёт запрос из имени класса и контекста родителя.
     */
    private function instantiateFromParent(string $class, ?PipelineContext $parent): RequestInterface
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $args = [];
        $parentRequest = $parent?->request;
        $parentData = $parentRequest ? get_object_vars($parentRequest) : [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $parentData)) {
                $args[$name] = $parentData[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[$name] = $parameter->getDefaultValue();
            } else {
                $args[$name] = null;
            }
        }

        return $reflection->newInstanceArgs($args);
    }
}
