<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Laravel\SdkServiceProvider;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Brahmic\ApiSutra\Tests\Stubs\Requests\DiRequest;
use Brahmic\ApiSutra\Tests\Stubs\Resolver\MultiServiceStubClient;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\Resolver\ClientResolver;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Acme\Discovery\DiscoveryClient;
use Acme\Discovery\Requests\DiscoveryRequest;

describe('SdkServiceProvider DI', function () {
    it('заполняет запрос через RequestFactory и привязывает клиента', function () {
        $app = new Container();
        $provider = new SdkServiceProvider($app);
        $provider->register();

        $app->instance(Request::class, Request::create('/di', 'GET', ['query' => 'value']));

        $registry = $app->make(ClientRegistry::class);
        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            new MockTransport(),
        );
        $registry->register($client, 'Brahmic\\ApiSutra\\Tests\\Stubs\\Requests');

        $request = $app->make(DiRequest::class);

        expect($request->query)->toBe('value');
        expect($request->hasClient())->toBeTrue();
        expect($request->getClient())->toBe($client);
    });

    it('регистрирует сервисы мегаклиента при резолве', function () {
        $app = new Container();
        $provider = new SdkServiceProvider($app);
        $provider->register();

        $client = new DiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );

        $app->singleton(MultiServiceStubClient::class, function () use ($client) {
            return new MultiServiceStubClient([$client]);
        });

        $app->make(MultiServiceStubClient::class);

        $registry = $app->make(ClientRegistry::class);
        $resolver = new ClientResolver($registry);
        $request = new DiscoveryRequest();

        expect($resolver->resolve($request))->toBe($client);
    });
});
