<?php

declare(strict_types=1);

use Acme\Discovery\DiscoveryClient;
use Acme\Discovery\Requests\DiscoveryRequest;
use Acme\Discovery\Resources\DiscoveryResourceRequest;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Execution\SendMode;
use Brahmic\ApiSutra\Resolver\ClassMapProvider;
use Brahmic\ApiSutra\Resolver\ClientDiscoveryCache;
use Brahmic\ApiSutra\Resolver\ClientDiscoveryService;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Brahmic\ApiSutra\Resolver\DiscoveryOptions;
use Brahmic\ApiSutra\Resolver\RequestNamespaceDetector;
use Brahmic\ApiSutra\Resolver\RequestScanner;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;
use Brahmic\ApiSutra\Result\ResultHandle;
use Brahmic\ApiSutra\Response\ClientResponse;
use Brahmic\ApiSutra\Tests\Support\SpyCache;
use Psr\SimpleCache\CacheInterface;
use Brahmic\ApiSutra\Enums\Discovery\DiscoveryCacheMode;
use Brahmic\ApiSutra\Tests\Stubs\Resolver\FixedCache;
use Brahmic\ApiSutra\Tests\Stubs\Resolver\NoScanClient;
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;

function resolveComposerChecksumForTest(): string
{
    $root = getcwd();
    if (!is_string($root)) {
        return '';
    }

    $paths = [
        $root . DIRECTORY_SEPARATOR . 'composer.lock',
        $root . DIRECTORY_SEPARATOR . 'composer.json',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            return sha1_file($path) ?: '';
        }
    }

    return '';
}

describe('ClientDiscoveryService', function () {
    it('использует кешированный список namespace', function () {
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache(new FixedCache(['Acme\\Cached\\Requests']));
        $service = new ClientDiscoveryService($registry, $detector, $cache);

        $client = new NoScanClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );

        $service->registerAuto($client, DiscoveryOptions::forceOn());

        expect($registry->resolve('Acme\\Cached\\Requests\\AnyRequest'))->toBe($client);
    });

    it('кеширует результат при включенном кеше', function () {
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $store = new SpyCache();
        $cache = new ClientDiscoveryCache($store);
        $service = new ClientDiscoveryService($registry, $detector, $cache);

        $client = new DiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );

        $service->registerAuto($client, DiscoveryOptions::forceOn(120));

        expect($registry->resolve(DiscoveryRequest::class))->toBe($client);
        expect($store->lastSetKey)->not->toBeNull();
        expect(str_starts_with((string) $store->lastSetKey, 'apisutra.discovery.'))->toBeTrue();
        expect($store->lastSetTtl)->toBe(120);
    });

    it('регистрирует несколько namespace при auto-discovery', function () {
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache();
        $service = new ClientDiscoveryService($registry, $detector, $cache);

        $client = new DiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );

        $service->registerAuto($client, DiscoveryOptions::forceOff());

        expect($registry->resolve(DiscoveryRequest::class))->toBe($client);
        expect($registry->resolve(DiscoveryResourceRequest::class))->toBe($client);
    });

    it('включает composer checksum в ключ кеша', function () {
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache();
        $service = new ClientDiscoveryService($registry, $detector, $cache);

        $options = new DiscoveryOptions(
            cacheMode: DiscoveryCacheMode::ForceOn,
            cacheKeyVersion: 'v1',
        );

        $method = new ReflectionMethod(ClientDiscoveryService::class, 'buildCacheKey');
        $method->setAccessible(true);

        $key = $method->invoke($service, DiscoveryClient::class, $options);
        $checksum = resolveComposerChecksumForTest();
        $expected = hash('sha256', DiscoveryClient::class . '|v1|' . $checksum);

        expect($key)->toBe($expected);
    });

    it('resolveBasePath использует ContainerProvider', function () {
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache();
        $service = new ClientDiscoveryService($registry, $detector, $cache);

        $provider = new class implements ContainerProviderInterface {
            #[\Override]
            public function bound(string $id): bool
            {
                return false;
            }

            #[\Override]
            public function make(string $id): ?object
            {
                return null;
            }

            #[\Override]
            public function basePath(): ?string
            {
                return '/tmp/apisutra';
            }

            #[\Override]
            public function environment(): ?string
            {
                return null;
            }

            #[\Override]
            public function isDebug(): ?bool
            {
                return null;
            }

            #[\Override]
            public function validatorFactory(): ?object
            {
                return null;
            }
        };

        ContainerProviderRegistry::set($provider);

        $method = new ReflectionMethod(ClientDiscoveryService::class, 'resolveBasePath');
        $method->setAccessible(true);

        $path = $method->invoke($service);

        expect($path)->toBe('/tmp/apisutra');
    });
});
