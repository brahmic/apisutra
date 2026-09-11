<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Pipeline\Pipeline;
use Brahmic\ApiSutra\Tests\Stubs\Requests\InvalidCompositeRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ThrowingCompositeRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ThrowingEndpointRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Pipeline skip flags', function () {
    it('пропускает валидацию и выполняет composite', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'User']),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $pipeline = resolvePipeline($client);
        $request = new InvalidCompositeRequest();
        $request->setClient($client);

        $result = $pipeline->execute($request, skipValidation: true);

        expect($result->isSuccess())->toBeTrue()
            ->and($transport->getRecorded())->toHaveCount(1);
    });

    it('пропускает composite и выполняет основной запрос', function () {
        $transport = new MockTransport();
        $transport->fake([
            InvalidCompositeRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $pipeline = resolvePipeline($client);
        $request = new InvalidCompositeRequest();
        $request->setClient($client);

        $result = $pipeline->execute($request, skipComposite: true, skipValidation: true);

        expect($result->isSuccess())->toBeTrue()
            ->and($transport->getRecorded())->toHaveCount(1);
    });

    it('останавливается на ошибке в composite и не вызывает транспорт', function () {
        $transport = new MockTransport();
        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $pipeline = resolvePipeline($client);
        $request = new ThrowingCompositeRequest();
        $request->setClient($client);

        $result = $pipeline->execute($request);

        expect($result->isFailed())->toBeTrue()
            ->and($transport->getRecorded())->toHaveCount(0);
    });

    it('останавливается на ошибке подготовки и не вызывает транспорт', function () {
        $transport = new MockTransport();
        $transport->fake([
            ThrowingEndpointRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $pipeline = resolvePipeline($client);
        $request = new ThrowingEndpointRequest();
        $request->setClient($client);

        $result = $pipeline->execute($request);

        expect($result->isFailed())->toBeTrue()
            ->and($transport->getRecorded())->toHaveCount(0);
    });
});

function resolvePipeline(TestClient $client): Pipeline
{
    $property = new ReflectionProperty(AbstractClient::class, 'pipeline');
    $property->setAccessible(true);

    $pipeline = $property->getValue($client);
    if (!$pipeline instanceof Pipeline) {
        throw new RuntimeException('Не удалось получить pipeline клиента');
    }

    return $pipeline;
}
