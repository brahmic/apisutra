<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Support;

use Brahmic\ApiSutra\RateLimiting\RateLimitBackendInterface;
use Brahmic\ApiSutra\RateLimiting\RateLimitDecision;
use Brahmic\ApiSutra\RateLimiting\RateLimitQuota;
use Closure;

final class ScriptedRateLimitBackend implements RateLimitBackendInterface
{
    public int $calls = 0;
    /** @var list<list<RateLimitQuota>> */
    public array $sets = [];
    /** @var list<int|null> */
    public array $timeouts = [];

    public function __construct(private readonly ?Closure $callback = null)
    {
    }

    public function tryAcquire(array $quotas, ?int $timeoutMs = null): RateLimitDecision
    {
        $this->calls++;
        $this->sets[] = $quotas;
        $this->timeouts[] = $timeoutMs;
        return $this->callback === null ? new RateLimitDecision(true) : ($this->callback)($quotas, $timeoutMs, $this->calls);
    }
}
