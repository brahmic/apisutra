<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Laravel\SdkServiceProvider;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Brahmic\ApiSutra\Tests\Stubs\Requests\DiRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ReadonlyDiRequest;
use Brahmic\ApiSutra\Tests\Stubs\Resolver\MultiServiceStubClient;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\Resolver\ClientResolver;
use Illuminate\Container\Container;
use Brahmic\ApiSutra\Laravel\RequestFactory;
use Brahmic\ApiSutra\Contracts\Interfaces\Factory\RequestFactoryInterface;
use Illuminate\Http\Request;
use Acme\Discovery\DiscoveryClient;
use Acme\Discovery\Requests\DiscoveryRequest;

describe('SdkServiceProvider DI', function () {
    it('сохраняет явные значения при DI и привязывает клиента', function () {
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

        $app->bind(DiRequest::class, static function (): DiRequest {
            $request = new DiRequest();
            $request->query = 'explicit';
            return $request;
        });
        $request = $app->make(DiRequest::class);

        expect($request->query)->toBe('explicit');
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

it('сохраняет bindings фабрики и реестра до регистрации provider', function (bool $resolved): void {
    $app = new Container();
    $factory = new RequestFactory();
    $registry = new ClientRegistry();
    $app->singleton(RequestFactoryInterface::class, static fn (): RequestFactory => $factory);
    $app->singleton(ClientRegistry::class, static fn (): ClientRegistry => $registry);
    if ($resolved) {
        $app->make(RequestFactoryInterface::class);
        $app->make(ClientRegistry::class);
    }
    (new SdkServiceProvider($app))->register();
    (new SdkServiceProvider($app))->register();
    expect($app->make(RequestFactoryInterface::class))->toBe($factory)
        ->and($app->make(ClientRegistry::class))->toBe($registry);
})->with([false, true]);

it('без HTTP контекста сохраняет привязанного клиента и runtime опции', function (): void {
    $app = new Container();
    (new SdkServiceProvider($app))->register();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new MockTransport());
    $request = (new DiRequest())->setClient($client);
    $request->query = 'explicit';
    $request->getOptions()->withCache(42);
    $options = $request->getOptions();
    $app->bind(DiRequest::class, static fn (): DiRequest => $request);
    expect($app->make(DiRequest::class))->toBe($request)
        ->and($request->query)->toBe('explicit')
        ->and($request->getClient())->toBe($client)
        ->and($request->getOptions())->toBe($options);
});

it('не пересоздаёт readonly запрос с обязательным конструктором из HTTP', function (): void {
    $app = new Container();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new MockTransport());
    $request = (new ReadonlyDiRequest('20'))->setClient($client);
    $app->instance(Request::class, Request::create('/items', 'GET', ['limit' => '500']));
    $app->bind(ReadonlyDiRequest::class, static fn (): ReadonlyDiRequest => $request);
    (new SdkServiceProvider($app))->register();
    expect($app->make(ReadonlyDiRequest::class))->toBe($request)
        ->and($request->limit)->toBe('20')
        ->and($request->getClient())->toBe($client);
    $explicit = $app->make(RequestFactoryInterface::class)->make(ReadonlyDiRequest::class, ['limit' => '500']);
    expect($explicit->limit)->toBe('500');
});
