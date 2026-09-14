<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\Rules;

use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use Brahmic\ApiSutra\Exceptions\Serialization\SerializationException;
use Closure;
use DateTimeInterface;
use JsonSerializable;
use Stringable;
use UnitEnum;

/** @internal Исключает receiver, сохраняя представление остальных plain-значений для JSON. */
final readonly class ReceiverOutput
{
    /** @param array<class-string, string> $receivers */
    public function __construct(private array $receivers)
    {
    }

    public function receiverFor(string $class): ?string
    {
        return $this->receivers[$class] ?? null;
    }

    public function contains(mixed $value): bool
    {
        return $this->receivers !== [] && $this->scan($value, [], 0);
    }

    /** @param callable(object): array<string, mixed> $dtoSerializer */
    public function project(mixed $value, callable $dtoSerializer): mixed
    {
        if ($this->receivers === []) {
            return $value;
        }
        return $this->rewrite($value, $dtoSerializer, [], 0)[1];
    }

    /** @param array<int, true> $ancestors */
    private function scan(mixed $value, array $ancestors, int $depth): bool
    {
        if (!is_array($value) && !is_object($value)) {
            return false;
        }
        if (is_object($value) && isset($this->receivers[$value::class])) {
            return true;
        }
        if ($this->opaque($value)) {
            return false;
        }
        $ancestors = $this->guard($value, $ancestors, $depth);
        foreach (is_object($value) ? get_object_vars($value) : $value as $child) {
            if ($this->scan($child, $ancestors, $depth + 1)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param callable(object): array<string, mixed> $dtoSerializer
     * @param array<int, true> $ancestors
     * @return array{bool, mixed}
     */
    private function rewrite(mixed $value, callable $dtoSerializer, array $ancestors, int $depth): array
    {
        if ((!is_array($value) && !is_object($value)) || $this->opaque($value)) {
            return [false, $value];
        }
        $ancestors = $this->guard($value, $ancestors, $depth);
        if ($value instanceof DtoInterface) {
            return $this->contains($value) ? [true, $dtoSerializer($value)] : [false, $value];
        }
        $receiver = is_object($value) ? ($this->receivers[$value::class] ?? null) : null;
        $fields = is_object($value) ? get_object_vars($value) : $value;
        $changed = $receiver !== null;
        if ($receiver !== null) {
            unset($fields[$receiver]);
        }
        foreach ($fields as $key => $child) {
            [$childChanged, $rewritten] = $this->rewrite($child, $dtoSerializer, $ancestors, $depth + 1);
            if ($childChanged) {
                $fields[$key] = $rewritten;
                $changed = true;
            }
        }
        if (!$changed) {
            return [false, $value];
        }
        // JSON-объект остаётся объектом при пустых или числовых свойствах.
        if (is_object($value) && ($receiver === null || array_is_list($fields))) {
            return [true, (object) $fields];
        }
        return [true, $fields];
    }

    private function opaque(mixed $value): bool
    {
        if ($value instanceof DtoInterface) {
            return false;
        }
        return $value instanceof JsonSerializable || $value instanceof Stringable
            || $value instanceof DateTimeInterface || $value instanceof UnitEnum || $value instanceof Closure
            || is_object($value) && method_exists($value, 'toArray');
    }

    /**
     * @param array<int, true> $ancestors
     * @return array<int, true>
     */
    private function guard(array|object $value, array $ancestors, int $depth): array
    {
        $id = is_object($value) ? spl_object_id($value) : null;
        if ($depth >= 512 || $id !== null && isset($ancestors[$id])) {
            throw new SerializationException('Циклическая ссылка или превышение глубины исходящего DTO');
        }
        if ($id !== null) {
            $ancestors[$id] = true;
        }
        return $ancestors;
    }
}
