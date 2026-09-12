<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pagination;

use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Enums\Pagination\PaginationMode;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * Правила выполнения пагинации.
 */
final readonly class PaginationRule
{
    private function __construct(
        public PaginationMode $mode,
        public ?int $pages = null,
        public ?int $from = null,
        public ?int $to = null,
        public FailStrategy $failStrategy = FailStrategy::FailAll,
    ) {
    }

    public static function single(): self
    {
        return new self(mode: PaginationMode::Single);
    }

    public static function all(FailStrategy $failStrategy = FailStrategy::FailAll): self
    {
        return new self(mode: PaginationMode::All, failStrategy: $failStrategy);
    }

    public static function pages(int $count, FailStrategy $failStrategy = FailStrategy::FailAll): self
    {
        if ($count < 1) {
            throw new ConfigurationException('Количество страниц должно быть больше 0');
        }

        return new self(mode: PaginationMode::Pages, pages: $count, failStrategy: $failStrategy);
    }

    public static function range(int $from, int $to, FailStrategy $failStrategy = FailStrategy::FailAll): self
    {
        if ($from < 1 || $to < $from) {
            throw new ConfigurationException('Некорректный диапазон страниц');
        }

        return new self(mode: PaginationMode::Range, from: $from, to: $to, failStrategy: $failStrategy);
    }

    public function isSingle(): bool
    {
        return $this->mode === PaginationMode::Single;
    }
}
