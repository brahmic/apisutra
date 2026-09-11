<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Support;

use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;

final class FakeSleeper implements SleeperInterface
{
    public int $calls = 0;
    public int $totalMs = 0;

    public function sleepMs(int $milliseconds): void
    {
        $this->calls++;
        $this->totalMs += max(0, $milliseconds);
    }
}
