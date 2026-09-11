<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Exceptions\Testing\MissingFixtureException;
use Brahmic\ApiSutra\Testing\MockClient;
use Brahmic\ApiSutra\Testing\MockConfig;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ProtectedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Transport testing', function () {
    afterEach(function (): void {
        MockClient::destroyGlobal();
    });

    it('MockClient::global перехватывает запросы', function () {
        MockClient::destroyGlobal();
        MockClient::global([
            SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'global']),
        ]);

        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 2, 'name' => 'local']),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);
        $result = $request->send()->raw();

        expect($result->data->name ?? null)->toBe('global');
    });

    it('record и playback используют фикстуры', function () {
        $path = sys_get_temp_dir() . '/apisutra-fixtures-' . uniqid();

        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 10, 'name' => 'recorded']),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $client->record($path);

        $request = new SimpleGetRequest('q');
        $request->setClient($client);
        $request->send()->raw();

        $files = glob($path . '/*.json') ?: [];
        expect($files)->not->toBeEmpty();

        $playbackClient = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            new MockTransport(),
        );
        $playbackClient->playback($path);

        $playbackRequest = new SimpleGetRequest('q');
        $playbackRequest->setClient($playbackClient);
        $result = $playbackRequest->send()->raw();

        expect($result->data->name ?? null)->toBe('recorded');

        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($path);
    });

    it('client->fake и assertSent работают', function () {
        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            new MockTransport(),
        );
        $client->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'ok']),
        ]);

        $request = new SimpleGetRequest('q');
        $request->setClient($client);
        $result = $request->send()->raw();

        $client->assertSent(SimpleGetRequest::class, null, 1);
        $client->assertNotSent(ProtectedRequest::class);
        expect($result->data->id ?? null)->toBe(1);
    });

    it('выбрасывает MissingFixtureException при отсутствии фикстуры', function () {
        $path = sys_get_temp_dir() . '/apisutra-missing-' . uniqid();
        @mkdir($path, 0777, true);

        MockConfig::setFixturePath($path);
        MockConfig::throwOnMissingFixtures();

        $transport = new MockTransport();
        $request = new SimpleGetRequest('q');
        $prepared = new \Brahmic\ApiSutra\VO\Http\PreparedRequest(
            method: Brahmic\ApiSutra\Enums\Http\HttpMethod::GET,
            url: 'https://api.test',
            meta: ['requestClass' => SimpleGetRequest::class],
        );

        expect(fn () => $transport->send($prepared))
            ->toThrow(MissingFixtureException::class);

        $reset = new ReflectionProperty(MockConfig::class, 'throwOnMissingFixtures');
        $reset->setAccessible(true);
        $reset->setValue(null, false);

        $fixturePath = new ReflectionProperty(MockConfig::class, 'fixturePath');
        $fixturePath->setAccessible(true);
        $fixturePath->setValue(null, null);

        @rmdir($path);
    });
});
