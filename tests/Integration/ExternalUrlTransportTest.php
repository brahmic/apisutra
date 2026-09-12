<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\MultipartUploadRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\LocalUrlServer;
use Brahmic\ApiSutra\Transport\GuzzleHttpClient;
use Brahmic\ApiSutra\Transport\HttpTransport;
use Brahmic\ApiSutra\VO\Files\FileInput;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\HttpFactory;

beforeEach(function (): void {
    if (!extension_loaded('curl') || !function_exists('proc_open')) {
        $this->markTestSkipped('Локальный HTTP-стенд требует ext-curl и proc_open');
    }
});

it('сохраняет точный request target и исключает defaults HTTP клиента', function (string $target): void {
    $server = new LocalUrlServer();
    try {
        $factory = new HttpFactory();
        $transport = new HttpTransport(new GuzzleHttpClient([
            'auth' => ['fixture-user', 'fixture-password'],
            'headers' => ['X-Provider-Secret' => 'fixture-secret'],
            'query' => ['secret' => 'fixture-query'],
            'body' => 'fixture-default-body',
            'cookies' => CookieJar::fromArray(['fixture_cookie' => 'fixture-cookie'], '127.0.0.1'),
        ]), $factory, $factory);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
        $result = (new CacheProbeRequest())->setClient($client)->withUrl($server->url . $target)->dataOrFail();
        expect($result['target'])->toBe($target)
            ->and($result['body'])->toBe('')
            ->and($result['headers'])->not->toHaveKey('authorization')->not->toHaveKey('cookie')->not->toHaveKey('x-provider-secret');
    } finally {
        $server->close();
    }
})->with(['/file/%2f?x=+&x=%20&&sig=fixture&', '/file?', '/a/../file?sig=fixture', '/?']);

it('не выполняет redirect на другой локальный origin', function (int $status): void {
    $server = new LocalUrlServer();
    try {
        $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), HttpTransport::createDefault());
        $request = (new CacheProbeRequest())->setClient($client);
        $result = $request->withUrl($server->url . '/redirect/' . $status)->send()->raw();
        expect($result->response->status)->toBe($status);
        $stats = $request->withUrl($server->url . '/stats')->dataOrFail();
        expect($stats['counts'][1])->toBe(0);
    } finally {
        $server->close();
    }
})->with([301, 302, 303, 307, 308]);

it('сохраняет absolute-form для HTTP proxy включая пустой query', function (): void {
    $server = new LocalUrlServer();
    try {
        $factory = new HttpFactory();
        $transport = new HttpTransport(new GuzzleHttpClient(['proxy' => $server->url]), $factory, $factory);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
        $url = 'http://destination.test/file?';
        $result = (new CacheProbeRequest())->setClient($client)->withUrl($url)->dataOrFail();
        expect($result['target'])->toBe($url);
    } finally {
        $server->close();
    }
});

it('передаёт multipart файл и явные заголовки до локального HTTP', function (): void {
    $server = new LocalUrlServer();
    try {
        $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), HttpTransport::createDefault());
        $request = new MultipartUploadRequest([FileInput::fromContent('fixture-file-content', 'test.txt')], 'fixture-comment');
        $data = $request->setClient($client)->withUrl($server->url . '/upload?sig=fixture&')->withHeader('X-Upload-Key', 'fixture-upload')->dataOrFail();
        expect($data['target'])->toBe('/upload?sig=fixture&')
            ->and($data['headers']['x-upload-key'])->toBe('fixture-upload')
            ->and($data['body'])->toContain('fixture-file-content', 'fixture-comment', 'filename="test.txt"');
    } finally {
        $server->close();
    }
});

it('отклоняет raw cURL overrides до HTTP защищаемого вызова', function (): void {
    $server = new LocalUrlServer();
    try {
        $factory = new HttpFactory();
        $transport = new HttpTransport(new GuzzleHttpClient(['curl' => [CURLOPT_USERPWD => 'fixture:secret']]), $factory, $factory);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);
        $result = (new CacheProbeRequest())->setClient($client)->withUrl($server->url . '/blocked')->send()->raw();
        expect($result->errors->first()->code->value)->toBe('configuration_error');
        $clean = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), HttpTransport::createDefault());
        $stats = (new CacheProbeRequest())->setClient($clean)->withUrl($server->url . '/stats')->dataOrFail();
        expect($stats['counts'])->toBe([1, 0]);
    } finally {
        $server->close();
    }
});
