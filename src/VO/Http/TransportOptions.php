<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\VO\Http;

use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Brahmic\ApiSutra\Timing\ExecutionBudget;

final readonly class TransportOptions
{
    public function __construct(
        public int $timeoutMs = 0,
        public int $connectTimeoutMs = 0,
        public ?ExecutionBudget $budget = null,
    ) {
        if ($timeoutMs < 0 || $connectTimeoutMs < 0) {
            throw new ConfigurationException('Транспортные таймауты должны быть >= 0');
        }
    }

    /** Пересчитывается непосредственно перед HTTP, включая ожидания собственного retry handler. */
    public function effective(): self
    {
        $remaining = $this->budget?->remainingMs();
        if ($remaining === 0) {
            throw new ExecutionDeadlineException('http');
        }
        $timeout = $remaining === null ? $this->timeoutMs
            : ($this->timeoutMs === 0 ? $remaining : min($this->timeoutMs, $remaining));
        $connect = $this->connectTimeoutMs;
        if ($timeout > 0 && $connect > 0) {
            $connect = min($connect, $timeout);
        }
        return new self($timeout, $connect, $this->budget);
    }

    public function hasLimits(): bool
    {
        return $this->timeoutMs > 0 || $this->connectTimeoutMs > 0 || $this->budget?->deadlineMs !== null;
    }
}
