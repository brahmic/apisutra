<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Config;

use Brahmic\ApiSutra\Enums\RateLimiting\BackoffStrategy;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Transport\ConnectionException;

final readonly class RetryConfig
{
    /**
     * @param array<int> $retryOn
     * @param array<string> $retryExceptions
     */
    public function __construct(
        public int $attempts = 3,
        public int $baseDelay = 100,
        public int $maxDelay = 10000,
        public BackoffStrategy $backoff = BackoffStrategy::Exponential,
        public bool $jitter = true,
        public array $retryOn = [408, 429, 500, 502, 503, 504],
        public array $retryExceptions = [ConnectionException::class],
        public ?int $totalTimeoutMs = null,
    ) {
        $this->validate();
    }

    /**
     * Валидировать параметры retry.
     */
    private function validate(): void
    {
        if ($this->attempts < 1) {
            throw new ConfigurationException('RetryConfig.attempts должен быть >= 1');
        }

        if ($this->baseDelay < 0) {
            throw new ConfigurationException('RetryConfig.baseDelay должен быть >= 0');
        }

        if ($this->maxDelay < $this->baseDelay) {
            throw new ConfigurationException('RetryConfig.maxDelay должен быть >= baseDelay');
        }

        if ($this->totalTimeoutMs !== null && $this->totalTimeoutMs < 1) {
            throw new ConfigurationException('RetryConfig.totalTimeoutMs должен быть >= 1');
        }
    }
}
