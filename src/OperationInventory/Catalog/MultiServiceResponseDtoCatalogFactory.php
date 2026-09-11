<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\OperationInventory\Catalog;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Inventory\OperationInventoryInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogProviderInterface;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\OperationInventory\CompositeOperationInventory;

/**
 * Фабрика multi-service каталога DTO.
 *
 * Что делает:
 * - собирает operationInventory() со всех сервис-клиентов
 * - складывает их в CompositeOperationInventory
 * - размечает каждый usage в ResponseDtoCatalog meaningful serviceClass
 *
 * Сами сервис-клиенты ничего не сканируют дополнительно — composite агрегирует
 * уже готовые inventory.
 */
final readonly class MultiServiceResponseDtoCatalogFactory
{
    public function fromMultiService(MultiServiceClientInterface $mega): ResponseDtoCatalog
    {
        return $this->fromServices($mega->services());
    }

    /**
     * @param array<int, ClientInterface> $services
     */
    public function fromServices(array $services): ResponseDtoCatalog
    {
        $inventories = [];
        $serviceByRequest = [];

        foreach ($services as $service) {
            $inventory = $this->resolveInventory($service);
            $inventories[] = $inventory;

            $serviceClass = $service::class;
            foreach ($inventory->all() as $operation) {
                $serviceByRequest[$operation->requestClass] ??= $serviceClass;
            }
        }

        return new ResponseDtoCatalog(
            inventory: new CompositeOperationInventory($inventories),
            serviceClassResolver: new MapServiceClassResolver($serviceByRequest),
        );
    }

    /**
     * Сливает каталоги произвольного набора провайдеров в один.
     *
     * Принимает на вход:
     * - одиночные клиенты (`AbstractClient` / `ClientInterface` + `ResponseDtoCatalogProviderInterface`)
     * - мегаклиенты (`MultiServiceClientInterface` + `ResponseDtoCatalogProviderInterface`)
     *   — раскрываются в свои services()
     *
     * Каждому usage проставляется serviceClass:
     * - для services() мегаклиента — FQCN сервиса
     * - для одиночного клиента — FQCN самого клиента
     *
     * Если один и тот же requestClass встречается у нескольких провайдеров,
     * выигрывает первый по порядку (детерминированно).
     */
    public function merge(ResponseDtoCatalogProviderInterface ...$providers): ResponseDtoCatalog
    {
        $inventories = [];
        $serviceByRequest = [];

        foreach ($providers as $provider) {
            foreach ($this->expandProvider($provider) as $serviceClass => $inventory) {
                $inventories[] = $inventory;
                foreach ($inventory->all() as $operation) {
                    $serviceByRequest[$operation->requestClass] ??= $serviceClass;
                }
            }
        }

        return new ResponseDtoCatalog(
            inventory: new CompositeOperationInventory($inventories),
            serviceClassResolver: new MapServiceClassResolver($serviceByRequest),
        );
    }

    /**
     * @return iterable<class-string, OperationInventoryInterface>
     */
    private function expandProvider(ResponseDtoCatalogProviderInterface $provider): iterable
    {
        if ($provider instanceof MultiServiceClientInterface) {
            foreach ($provider->services() as $service) {
                yield $service::class => $this->resolveInventory($service);
            }

            return;
        }

        if ($provider instanceof ClientInterface) {
            yield $provider::class => $this->resolveInventory($provider);

            return;
        }

        throw new ConfigurationException(sprintf(
            'Провайдер "%s" не поддерживается merge(): должен быть ClientInterface или MultiServiceClientInterface',
            $provider::class,
        ));
    }

    private function resolveInventory(ClientInterface $service): OperationInventoryInterface
    {
        if ($service instanceof AbstractClient) {
            return $service->operationInventory();
        }

        if (method_exists($service, 'operationInventory')) {
            /** @var mixed $inventory */
            $inventory = $service->{'operationInventory'}();
            if ($inventory instanceof OperationInventoryInterface) {
                return $inventory;
            }
        }

        throw new ConfigurationException(sprintf(
            'Сервис-клиент "%s" не предоставляет OperationInventoryInterface через operationInventory()',
            $service::class,
        ));
    }
}
