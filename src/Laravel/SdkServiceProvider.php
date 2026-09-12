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
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface as PsrRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface as PsrStreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;

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
        // Маркер принадлежит контейнеру приложения, а не глобальному процессу worker.
        $marker = self::class . '.registered';
        if ($this->app->bound($marker)) {
            return;
        }
        $this->app->instance($marker, true);
        $this->app->singletonIf(RequestFactoryInterface::class, function () {
            return new RequestFactory();
        });
        $this->app->singletonIf(ClientResponseAdapterInterface::class, function () {
            return new ClientResponseAdapter();
        });
        $this->app->singletonIf(ClientRegistry::class, function () {
            return new ClientRegistry();
        });
        $this->app->singletonIf(ClassMapProvider::class, function () {
            return new ClassMapProvider();
        });
        $this->app->singletonIf(RequestScanner::class, function ($app) {
            return new RequestScanner($app->make(ClassMapProvider::class));
        });
        $this->app->singletonIf(RequestNamespaceDetector::class, function ($app) {
            return new RequestNamespaceDetector($app->make(RequestScanner::class));
        });
        $this->app->singletonIf(ClientDiscoveryCache::class, function () {
            $store = null;
            if ($this->app->bound(CacheInterface::class)) {
                $store = $this->app->make(CacheInterface::class);
            }
            return new ClientDiscoveryCache($store);
        });
        $this->app->singletonIf(ClientDiscoveryService::class, function ($app) {
            return new ClientDiscoveryService(
                $app->make(ClientRegistry::class),
                $app->make(RequestNamespaceDetector::class),
                $app->make(ClientDiscoveryCache::class),
            );
        });
        $this->app->singletonIf(ClientResolverInterface::class, function ($app) {
            return new ClientResolver($app->make(ClientRegistry::class));
        });
        $this->app->singletonIf(ServiceRegistrar::class, function ($app) {
            return new ServiceRegistrar(
                $app->make(ClientRegistry::class),
                $app->make(RequestNamespaceDetector::class),
            );
        });
        $this->registerDefaultTransport();

        $this->app->resolving(MultiServiceClientInterface::class, function (MultiServiceClientInterface $object, $app): void {

            $registrar = $app->make(ServiceRegistrar::class);
            if ($registrar instanceof ServiceRegistrar) {
                $registrar->register($object->services());
            }
        });

        $this->app->resolving(AbstractRequest::class, function (AbstractRequest $object, $app): void {

            $this->resolveClient($object, $app);
        });
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

        $this->app->singletonIf(TransportInterface::class, function ($app) {
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
        if (!$client instanceof PsrClientInterface) {
            throw new ConfigurationException('Binding PSR-18 клиента должен реализовывать ClientInterface');
        }
        return $client;
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
        if (!$factory instanceof PsrRequestFactoryInterface) {
            throw new ConfigurationException('Binding PSR-17 request factory имеет неверный тип');
        }
        return $factory;
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
        if (!$factory instanceof PsrStreamFactoryInterface) {
            throw new ConfigurationException('Binding PSR-17 stream factory имеет неверный тип');
        }
        return $factory;
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
