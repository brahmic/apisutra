<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Resolver;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Resolver\RequestNamespaceProviderInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

final readonly class ServiceRegistrar
{
    public function __construct(
        private ClientRegistry $registry,
        private RequestNamespaceDetector $detector,
    ) {}

    /**
     * Зарегистрировать набор сервис‑клиентов в реестре.
     *
     * @param array<int, ClientInterface> $clients
     */
    public function register(array $clients): void
    {
        /**
         * @var array<string, bool> $processed
         */
        static $processed = [];

        foreach ($clients as $client) {
            if (!$client instanceof ClientInterface) {
                throw new ConfigurationException('Ожидается ClientInterface в списке сервисов');
            }

            $key = spl_object_hash($client);
            if (isset($processed[$key])) {
                continue;
            }
            $processed[$key] = true;

            $namespaces = $client instanceof RequestNamespaceProviderInterface
                ? $client->requestNamespaces()
                : $this->detector->detect($client);

            if ($namespaces === []) {
                $class = $client::class;
                throw new ConfigurationException("Не найдены namespace запросов клиента '{$class}'");
            }

            foreach ($namespaces as $namespace) {
                $this->registry->register($client, $namespace);
            }
        }
    }
}
