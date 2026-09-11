<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Inventory;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Tests\Stubs\Inventory\Requests\InventoryStatusRequest;
use Brahmic\ApiSutra\Tests\Stubs\Inventory\Resources\InventoryOrdersResource;
use Brahmic\ApiSutra\Tests\Stubs\Inventory\Resources\InventoryReportsResource;

final class InventoryCallPathClient extends AbstractClient
{
    public function __construct(ClientConfig $config, TransportInterface $transport)
    {
        parent::__construct($config, $transport);
    }

    public function status(): InventoryStatusRequest
    {
        return (new InventoryStatusRequest())->setClient($this);
    }

    public function orders(): InventoryOrdersResource
    {
        return new InventoryOrdersResource($this);
    }

    public function reports(): InventoryReportsResource
    {
        return new InventoryReportsResource($this);
    }

    public function v1(): InventoryVersionRouter
    {
        return new InventoryVersionRouter($this, 'v1');
    }

    public function v2(): InventoryVersionRouter
    {
        return new InventoryVersionRouter($this, 'v2');
    }

    public function useVersion(string $version): InventoryVersionRouter
    {
        return new InventoryVersionRouter($this, $version);
    }
}
