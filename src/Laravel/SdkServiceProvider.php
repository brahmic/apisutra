<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Laravel;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientResolverInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Factory\RequestFactoryInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Response\ClientResponseAdapterInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Resolver\ClassMapProvider;
use Brahmic\ApiSutra\Resolver\ClientDiscoveryCache;
use Brahmic\ApiSutra\Resolver\ClientDiscoveryService;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Brahmic\ApiSutra\Resolver\ClientResolver;
use Brahmic\ApiSutra\Resolver\RequestNamespaceDetector;
use Brahmic\ApiSutra\Resolver\RequestScanner;
use Brahmic\ApiSutra\Resolver\ServiceRegistrar;
use Brahmic\ApiSutra\Transport\GuzzleHttpClient;
use Brahmic\ApiSutra\Transport\HttpTransport;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory as GuzzleHttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface as PsrRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface as PsrStreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;

/**
 * ServiceProvider пакета ApiSutra.
 *
 * Регистрирует сервисы discovery, реестр клиентов и авто‑резолвинг запросов,
 * чтобы запросы могли работать через DI в контроллерах.
 */
final class SdkServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрировать зависимости пакета и hooks для DI.
     */
    public function register(): void
    {
        $this->app->singleton(RequestFactoryInterface::class, function () {
            return new RequestFactory();
        });
        $this->app->singleton(ClientResponseAdapterInterface::class, function () {
            return new ClientResponseAdapter();
        });
        $this->app->singleton(ClientRegistry::class, function () {
            return new ClientRegistry();
        });
        $this->app->singleton(ClassMapProvider::class, function () {
            return new ClassMapProvider();
        });
        $this->app->singleton(RequestScanner::class, function ($app) {
            return new RequestScanner($app->make(ClassMapProvider::class));
        });
        $this->app->singleton(RequestNamespaceDetector::class, function ($app) {
            return new RequestNamespaceDetector($app->make(RequestScanner::class));
        });
        $this->app->singleton(ClientDiscoveryCache::class, function () {
            $store = null;
            if ($this->app->bound(CacheInterface::class)) {
                $store = $this->app->make(CacheInterface::class);
            }
            return new ClientDiscoveryCache($store);
        });
        $this->app->singleton(ClientDiscoveryService::class, function ($app) {
            return new ClientDiscoveryService(
                $app->make(ClientRegistry::class),
                $app->make(RequestNamespaceDetector::class),
                $app->make(ClientDiscoveryCache::class),
            );
        });
        $this->app->singleton(ClientResolverInterface::class, function ($app) {
            return new ClientResolver($app->make(ClientRegistry::class));
        });
        $this->app->singleton(ServiceRegistrar::class, function ($app) {
            return new ServiceRegistrar(
                $app->make(ClientRegistry::class),
                $app->make(RequestNamespaceDetector::class),
            );
        });
        $this->registerDefaultTransport();

        $this->app->resolving(function (object $object, $app): void {
            if (!$object instanceof MultiServiceClientInterface) {
                return;
            }

            $registrar = $app->make(ServiceRegistrar::class);
            if ($registrar instanceof ServiceRegistrar) {
                $registrar->register($object->services());
            }
        });

        $this->app->resolving(function (object $object, $app): void {
            if (!$object instanceof AbstractRequest) {
                return;
            }

            $factory = $app->make(RequestFactoryInterface::class);
            $httpRequest = $app->make(Request::class);
            $filled = $factory->make($object::class, $httpRequest);

            $this->copyRequestState($object, $filled);
            $this->resolveClient($object, $app);
        });
    }

    /**
     * Перенести заполненные данные запроса из фабрики в DI‑экземпляр.
     *
     * Копируются только изменяемые свойства, чтобы не ломать readonly‑контракты.
     */
    private function copyRequestState(AbstractRequest $target, AbstractRequest $source): void
    {
        $reflection = new ReflectionClass($source);
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic() || $property->isReadOnly()) {
                continue;
            }

            $property->setAccessible(true);
            $property->setValue($target, $property->getValue($source));
        }
    }

    /**
     * Привязать клиента к запросу, если он ещё не установлен.
     */
    private function resolveClient(AbstractRequest $request, mixed $app): void
    {
        if (!$request->hasClient() && is_object($app) && method_exists($app, 'make')) {
            $resolver = $app->make(ClientResolverInterface::class);
            if ($resolver instanceof ClientResolverInterface) {
                $request->setClient($resolver->resolve($request));
            }
        }
    }

    private function registerDefaultTransport(): void
    {
        if ($this->app->bound(TransportInterface::class)) {
            return;
        }

        $this->app->singleton(TransportInterface::class, function ($app) {
            return $this->resolveDefaultTransport($app);
        });
    }

    private function resolveDefaultTransport(mixed $app): TransportInterface
    {
        $client = $this->resolvePsrClient($app);
        $defaultFactory = $this->resolveDefaultPsrFactory();
        $requestFactory = $this->resolvePsrRequestFactory($app) ?? $defaultFactory;
        $streamFactory = $this->resolvePsrStreamFactory($app) ?? $defaultFactory;

        if (
            $client instanceof PsrClientInterface
            && $requestFactory instanceof PsrRequestFactoryInterface
            && $streamFactory instanceof PsrStreamFactoryInterface
        ) {
            return new HttpTransport($client, $requestFactory, $streamFactory);
        }

        $guzzle = $this->resolveGuzzleClient();
        if ($guzzle instanceof PsrClientInterface) {
            $factory = $defaultFactory ?? new GuzzleHttpFactory();
            return new HttpTransport($guzzle, $factory, $factory);
        }

        throw new ConfigurationException(
            'Транспорт не настроен. Передайте TransportInterface через контейнер '
            . 'или установите PSR-18 клиент (например, guzzlehttp/guzzle).',
        );
    }

    private function resolvePsrClient(mixed $app): ?PsrClientInterface
    {
        if (!is_object($app) || !method_exists($app, 'bound') || !method_exists($app, 'make')) {
            return null;
        }

        if (!$app->bound(PsrClientInterface::class)) {
            return null;
        }

        $client = $app->make(PsrClientInterface::class);
        return $client instanceof PsrClientInterface ? $client : null;
    }

    private function resolvePsrRequestFactory(mixed $app): ?PsrRequestFactoryInterface
    {
        if (!is_object($app) || !method_exists($app, 'bound') || !method_exists($app, 'make')) {
            return null;
        }

        if (!$app->bound(PsrRequestFactoryInterface::class)) {
            return null;
        }

        $factory = $app->make(PsrRequestFactoryInterface::class);
        return $factory instanceof PsrRequestFactoryInterface ? $factory : null;
    }

    private function resolvePsrStreamFactory(mixed $app): ?PsrStreamFactoryInterface
    {
        if (!is_object($app) || !method_exists($app, 'bound') || !method_exists($app, 'make')) {
            return null;
        }

        if (!$app->bound(PsrStreamFactoryInterface::class)) {
            return null;
        }

        $factory = $app->make(PsrStreamFactoryInterface::class);
        return $factory instanceof PsrStreamFactoryInterface ? $factory : null;
    }

    private function resolveDefaultPsrFactory(): ?GuzzleHttpFactory
    {
        if (!class_exists(GuzzleHttpFactory::class)) {
            return null;
        }

        return new GuzzleHttpFactory();
    }

    private function resolveGuzzleClient(): ?PsrClientInterface
    {
        if (!class_exists(GuzzleClient::class)) {
            return null;
        }

        return new GuzzleHttpClient();
    }
}
