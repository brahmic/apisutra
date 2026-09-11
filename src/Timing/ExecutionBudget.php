<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Timing;

use Brahmic\ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Throwable;

/** Один абсолютный срок; дочернее выполнение может только сократить его. */
final readonly class ExecutionBudget
{
    public ?int $deadlineMs;
    public ClockInterface $clock;

    public function __construct(ClockInterface $clock, ?int $limitMs = null, ?self $parent = null, ?int $startedMs = null)
    {
        $this->clock = $parent?->clock ?? $clock;
        $start = $startedMs ?? $this->clock->monotonicMs();
        if ($limitMs !== null && ($limitMs < 1 || $limitMs > PHP_INT_MAX - $start)) {
            throw new ConfigurationException('Недопустимый общий бюджет выполнения');
        }
        $own = $limitMs === null ? null : $start + $limitMs;
        $this->deadlineMs = $parent?->deadlineMs === null ? $own
            : ($own === null ? $parent->deadlineMs : min($own, $parent->deadlineMs));
    }

    public function remainingMs(): ?int
    {
        return $this->deadlineMs === null ? null : max(0, $this->deadlineMs - $this->clock->monotonicMs());
    }

    public function check(string $stage, ?Throwable $previous = null): void
    {
        if ($this->remainingMs() === 0) {
            throw new ExecutionDeadlineException($stage, $previous);
        }
    }

    public function wait(int $milliseconds, SleeperInterface $sleeper, string $stage): void
    {
        $this->check($stage);
        $remaining = $this->remainingMs();
        if ($remaining !== null && $milliseconds >= $remaining) {
            throw new ExecutionDeadlineException($stage);
        }
        if ($milliseconds > 0) {
            $sleeper->sleepMs($milliseconds);
        }
        $this->check($stage);
    }
}
