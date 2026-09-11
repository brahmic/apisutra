<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Timing\SystemClock;

final class TestClient extends AbstractClient
{
    public function __construct(ClientConfig $config, TransportInterface $transport, ?SleeperInterface $sleeper = null, ClockInterface $clock = new SystemClock())
    {
        parent::__construct($config, $transport, sleeper: $sleeper, clock: $clock);
    }
}
