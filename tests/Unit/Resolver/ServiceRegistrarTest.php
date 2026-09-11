<?php

declare(strict_types=1);

use Acme\Discovery\DiscoveryClient;
use Acme\Discovery\Requests\DiscoveryRequest;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Resolver\ClassMapProvider;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Brahmic\ApiSutra\Resolver\ClientResolver;
use Brahmic\ApiSutra\Resolver\RequestNamespaceDetector;
use Brahmic\ApiSutra\Resolver\RequestScanner;
use Brahmic\ApiSutra\Resolver\ServiceRegistrar;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PlainRequest;
use Brahmic\ApiSutra\Tests\Stubs\Resolver\NamespaceProviderClient;
describe('ServiceRegistrar', function () {
    it('регистрирует сервисы через auto-detect', function () {
        $client = new DiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $registrar = new ServiceRegistrar($registry, $detector);

        $registrar->register([$client]);

        $resolver = new ClientResolver($registry);
        $request = new DiscoveryRequest();

        expect($resolver->resolve($request))->toBe($client);
    });

    it('регистрирует сервисы через RequestNamespaceProviderInterface', function () {
        $client = new NamespaceProviderClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );
        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $registrar = new ServiceRegistrar($registry, $detector);

        $registrar->register([$client]);

        $resolver = new ClientResolver($registry);
        $request = new PlainRequest('query');

        expect($resolver->resolve($request))->toBe($client);
    });
});
