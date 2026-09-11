<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Transport\HttpTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Brahmic\ApiSutra\Tests\Stubs\Core\TestHttpClient;

describe('HttpTransport', function () {
    it('sendAsync возвращает Promise с ответом', function () {
        $client = new TestHttpClient();
        $factory = new HttpFactory();
        $transport = new HttpTransport($client, $factory, $factory);

        $prepared = new PreparedRequest(
            method: HttpMethod::GET,
            url: 'https://api.test/example',
        );

        $response = $transport->sendAsync($prepared)->wait();

        expect($response->status)->toBe(200);
        expect($client->lastRequest)->toBeInstanceOf(RequestInterface::class);
    });
});
