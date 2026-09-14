<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\Rules;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\ScopedCastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ScopedDefaultValueProviderInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Closure;

final class HydrationScope
{
    private ?Closure $hydrateNode = null;
    private ?PipelineContext $pipelineContext = null;
    private bool $tracking = false;
    private int $depth = 0;
    /** @var array<int, true> */
    private array $ancestors = [];
    private SourceLocation $location;

    /** @internal Область создаётся и привязывается гидратором для одного корневого вызова. */
    public function __construct()
    {
        $this->location = new SourceLocation();
    }

    /**
     * @internal
     * @param Closure(array|object, string, self): object $hydrate
     */
    public static function bind(Closure $hydrate, ?PipelineContext $context, bool $tracking): self
    {
        $scope = new self();
        $scope->hydrateNode = $hydrate;
        $scope->pipelineContext = $context;
        $scope->tracking = $tracking;
        if ($context?->hydrationSourceTransformed) {
            $scope->location = $scope->location->boundary();
        }
        return $scope;
    }

    public function hydrate(array|object $data, string $class): object
    {
        return $this->boundary(fn (): object => $this->hydrateDto($data, $class));
    }

    /** @return list<object> */
    public function hydrateCollection(array $items, string $class): array
    {
        return $this->boundary(fn (): array => $this->node($items, function () use ($items, $class): array {
            $result = [];
            foreach ($items as $item) {
                try {
                    if (!is_array($item) && !is_object($item)) {
                        throw HydrationException::invalidValue('unexpected_response_shape', $class, get_debug_type($item));
                    }
                    $result[] = $this->hydrateDto($item, $class);
                } catch (HydrationException $exception) {
                    throw $exception->prependPath('[' . count($result) . ']');
                }
            }
            return $result;
        }));
    }

    public function context(): ?PipelineContext
    {
        return $this->pipelineContext;
    }

    /** @internal Вход ядра сохраняет известное происхождение дочернего узла. */
    public function hydrateDto(array|object $data, string $class): object
    {
        if ($this->hydrateNode === null) {
            throw new ConfigurationException('HydrationScope должен быть создан гидратором');
        }
        return ($this->hydrateNode)($data, $class, $this);
    }

    /**
     * @internal
     * @template T
     * @param Closure(): T $operation
     * @return T
     */
    public function node(array|object $data, Closure $operation): mixed
    {
        if (!$this->tracking) {
            return $operation();
        }
        if ($this->depth >= 512) {
            throw $this->annotate(HydrationException::invalidValue('hydration_depth_exceeded', 'depth <= 512', 'deeper'));
        }
        $id = is_object($data) ? spl_object_id($data) : null;
        if ($id !== null && isset($this->ancestors[$id])) {
            throw $this->annotate(HydrationException::invalidValue('cyclic_hydration_input', 'acyclic input', 'cycle'));
        }
        $this->depth++;
        if ($id !== null) {
            $this->ancestors[$id] = true;
        }
        try {
            return $operation();
        } catch (HydrationException $exception) {
            throw $this->annotate($exception);
        } finally {
            $this->depth--;
            if ($id !== null) {
                unset($this->ancestors[$id]);
            }
        }
    }

    /** @internal */
    public function location(): SourceLocation
    {
        return $this->location;
    }

    /**
     * @internal
     * @template T
     * @param Closure(): T $operation
     * @return T
     */
    public function at(SourceLocation $location, Closure $operation): mixed
    {
        $previous = $this->location;
        $this->location = $location;
        try {
            return $operation();
        } catch (HydrationException $exception) {
            throw $this->annotate($exception);
        } finally {
            $this->location = $previous;
        }
    }

    /**
     * @internal
     * @template T
     * @param Closure(): T $operation
     * @return T
     */
    public function boundary(Closure $operation): mixed
    {
        $location = $this->location->boundary();
        try {
            return $this->at($location, $operation);
        } catch (HydrationException $exception) {
            // Самостоятельный гидратор внутри обработчика не знает происхождения его входа.
            if ($this->tracking && $exception->sourcePathKind !== SourcePathKind::Boundary) {
                throw $exception->withSource($location);
            }
            throw $exception;
        }
    }

    /** @internal */
    public function annotate(HydrationException $exception): HydrationException
    {
        return $this->tracking && $exception->sourcePathKind === null
            ? $exception->withSource($this->location)
            : $exception;
    }

    /** @internal Одинаковый вызов обработчика для атрибута, профиля и внешнего descriptor. */
    public function cast(CastInterface $cast, mixed $value): mixed
    {
        return $this->boundary(fn (): mixed => $cast instanceof ScopedCastInterface
            ? $cast->hydrateInScope($value, $this)
            : $cast->hydrate($value, $this->pipelineContext));
    }

    /**
     * @internal
     * @param array<string, mixed> $source
     */
    public function provide(DefaultValueProviderInterface $provider, mixed $value, ValueState $state, array $source): mixed
    {
        return $this->boundary(fn (): mixed => $provider instanceof ScopedDefaultValueProviderInterface
            ? $provider->resolveInScope($value, $state, $source, $this)
            : $provider->resolve($value, $state, $source, $this->pipelineContext));
    }
}
