<?php

declare(strict_types=1);

namespace Integration;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Integration\First\Client as FirstClient;
use Integration\Second\Client as SecondClient;

final class ApplicationProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TransportInterface::class, static function (): MockTransport {
            $transport = new MockTransport();
            $transport->fake(['*' => MockResponse::success(['ok' => true])]);
            return $transport;
        });
        $this->app->singleton(FirstClient::class, static fn (Application $app): FirstClient => new FirstClient(
            new ClientConfig(baseUrl: 'https://first.fixture.test'), $app->make(TransportInterface::class),
        ));
        $this->app->singleton(SecondClient::class, static fn (Application $app): SecondClient => new SecondClient(
            new ClientConfig(baseUrl: 'https://second.fixture.test'), $app->make(TransportInterface::class),
        ));
    }

    public function boot(ClientRegistry $registry): void
    {
        $registry->register($this->app->make(FirstClient::class), 'Integration\\First');
        $registry->register($this->app->make(SecondClient::class), 'Integration\\Second');
    }
}
