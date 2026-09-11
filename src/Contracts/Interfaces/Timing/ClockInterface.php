<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Timing;

interface ClockInterface
{
    public function monotonicMs(): int;
    public function unixTime(): int;
}
