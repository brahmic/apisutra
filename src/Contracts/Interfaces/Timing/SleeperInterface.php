<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Timing;

interface SleeperInterface
{
    public function sleepMs(int $milliseconds): void;
}
