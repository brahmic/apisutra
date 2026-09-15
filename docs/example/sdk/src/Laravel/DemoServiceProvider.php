<?php

declare(strict_types=1);

namespace Example\Records\Laravel;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\DemoClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class DemoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DemoClient::class, static function (Application $app): DemoClient {
            $baseUrl = (string) $app['config']->get('records.base_url', 'https://records.example.test');
            $client = new DemoClient(
                ClientConfigFactory::create($baseUrl),
                $app->make(TransportInterface::class),
            );
            $app->make(ClientRegistry::class)->register($client, 'Example\\Records\\Resources');

            return $client;
        });
    }
}
