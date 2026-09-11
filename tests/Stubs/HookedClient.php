<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Hooks\HookRegistry;

final class HookedClient extends AbstractClient
{
    public function __construct(ClientConfig $config, TransportInterface $transport, HookRegistry $hooks)
    {
        parent::__construct($config, $transport, $hooks);
    }

    public function hooks(): HookRegistry
    {
        return parent::hooks();
    }
}
