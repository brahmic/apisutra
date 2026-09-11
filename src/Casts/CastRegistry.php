<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Casts;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use RuntimeException;

final class CastRegistry
{
    private static ?self $global = null;

    /**
     * @var array<string, CastInterface|class-string<CastInterface>> Реестр кастов по типам.
     */
    private array $casts = [];

    /**
     * Глобальный registry — для Dto::from() без context
     */
    public static function global(): self
    {
        return self::$global ??= new self();
    }

    public function get(string $type): ?CastInterface
    {
        if (!array_key_exists($type, $this->casts)) {
            return null;
        }

        $cast = $this->casts[$type];
        if ($cast instanceof CastInterface) {
            return $cast;
        }

        if (is_string($cast) && class_exists($cast)) {
            $instance = new $cast();
            if (!$instance instanceof CastInterface) {
                throw new RuntimeException("Каст {$cast} должен реализовывать CastInterface");
            }
            $this->casts[$type] = $instance;
            return $instance;
        }

        return null;
    }

    /**
     * @param CastInterface|class-string<CastInterface> $cast Каст или класс каста.
     */
    public function register(string $type, CastInterface|string $cast): void
    {
        $this->casts[$type] = $cast;
    }

    public function isEmpty(): bool
    {
        return $this->casts === [];
    }
}
