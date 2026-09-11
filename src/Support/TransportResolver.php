<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Support;

use Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

final class TransportResolver
{
    public static function resolve(
        ?TransportInterface $transport = null,
        ?ContainerProviderInterface $containerProvider = null,
    ): TransportInterface {
        if ($transport instanceof TransportInterface) {
            return $transport;
        }

        $provider = ContainerProviderRegistry::resolve($containerProvider);
        if (!$provider->bound(TransportInterface::class)) {
            throw new ConfigurationException(
                'TransportInterface не найден в контейнере. Передайте transport явно.',
            );
        }

        $resolved = $provider->make(TransportInterface::class);
        if (!$resolved instanceof TransportInterface) {
            throw new ConfigurationException(
                'Контейнер вернул некорректный transport. Ожидался экземпляр TransportInterface.',
            );
        }

        return $resolved;
    }
}
