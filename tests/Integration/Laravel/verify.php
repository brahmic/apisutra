<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Factory\RequestFactoryInterface;
use Brahmic\ApiSutra\Laravel\SdkServiceProvider;
use Brahmic\ApiSutra\RateLimiting\Backends\PhpRedisRateLimitBackend;
use Brahmic\ApiSutra\RateLimiting\RateLimitQuota;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;
use Example\Records\DemoClient;
use Example\Records\Laravel\DemoServiceProvider;
use Example\Records\Resources\Records\Get\GetRecordRequest;
use Integration\First\Client;
use Integration\First\ItemsRequest;
use Integration\Second\Client as SecondClient;
use Integration\Second\ItemsRequest as SecondRequest;
use Integration\ProbeJob;

$loader = require __DIR__ . '/../../../.laravel-integration/vendor/autoload.php';
$loader->addPsr4('Example\\Records\\', __DIR__ . '/../../../docs/example/sdk/src/');

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach ([false, true] as $cached) {
    $app = require __DIR__ . '/bootstrap/app.php';
    $console = $app->make(ConsoleKernel::class);
    $console->bootstrap();
    check($app->getProvider(SdkServiceProvider::class) !== null, 'Package discovery не подключил SDK');
    check($app->configurationIsCached() === $cached, 'Неверный режим config cache');
    if (getenv('APISUTRA_TEST_REDIS') === '1') {
        $backend = $app->make(PhpRedisRateLimitBackend::class);
        $quota = new RateLimitQuota('laravel-' . bin2hex(random_bytes(8)), 1, 1000);
        check($backend->tryAcquire([$quota])->granted, 'Laravel Redis не выдал разрешение');
        check(!$backend->tryAcquire([$quota])->granted, 'Laravel Redis не сохранил общий счётчик');
        check($app->make(PhpRedisRateLimitBackend::class) === $backend, 'Backend пересоздан');
    }
    $client = $app->make(Client::class);
    $request = $app->make(ItemsRequest::class);
    check($request->limit === '20' && $request->getClient() === $client, 'DI изменил значения или клиента');
    check($app->make(SecondRequest::class)->getClient() === $app->make(SecondClient::class), 'Перепутаны клиенты');
    $transport = $app->make(TransportInterface::class);
    $app->register(SdkServiceProvider::class);
    check($app->make(TransportInterface::class) === $transport, 'Повторная регистрация заменила binding');
    $kernel = $app->make(HttpKernel::class);
    $incoming = Request::create('/probe?limit=500');
    $response = $kernel->handle($incoming);
    check($response->getStatusCode() === 200 && json_decode($response->getContent(), true) === ['limit' => '20', 'success' => true], 'HTTP DI подменил limit: ' . $response->getContent());
    $kernel->terminate($incoming, $response);
    $explicit = $app->make(RequestFactoryInterface::class)->make(ItemsRequest::class, $incoming);
    check($explicit->limit === '500', 'Явная фабрика не перенесла входящие данные');
    $app->make(Dispatcher::class)->dispatchSync(new ProbeJob('15'));
    $app->make(Dispatcher::class)->dispatchSync(new ProbeJob('30'));
    check($app->make(ItemsRequest::class)->limit === '20', 'Worker сохранил состояние задания');
    check($console->call('about', ['--only' => 'environment']) === 0, 'Artisan не запускается');
    // Проверяем опубликованный binding SDK с настоящим контейнером Laravel.
    $exampleTransport = new MockTransport();
    $exampleTransport->preventStrayRequests();
    $exampleTransport->fake([GetRecordRequest::class => MockResponse::success([
        'data' => ['record_id' => 7, 'title' => 'Laravel', 'new_field' => false],
    ])]);
    $app->instance(TransportInterface::class, $exampleTransport);
    $app['config']->set('records.base_url', 'https://laravel.example.test/v2');
    $app->register(DemoServiceProvider::class);
    $demo = $app->make(DemoClient::class);
    check($demo->getConfig()->baseUrl === 'https://laravel.example.test/v2', 'Пример потерял конфигурацию SDK');
    $record = $demo->records()->get(7)->dataOrFail();
    check($record->id === 7 && $record->_extra === ['new_field' => false], 'Binding примера потерял транспорт или правила');
    check($app->make(DemoClient::class) === $demo, 'Binding примера не singleton');
    if (!$cached) {
        check($console->call('config:cache') === 0, 'config:cache не прошёл');
    } else {
        check($console->call('config:clear') === 0, 'config:clear не прошёл');
    }
    $app->flush();
}
echo "Laravel 12: discovery, HTTP, explicit factory, two clients, Artisan, sequential jobs, config:cache — OK.\n";
