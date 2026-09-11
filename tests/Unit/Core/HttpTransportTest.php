<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Transport\HttpTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Core\PsrNetworkFailure;
use Brahmic\ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use Brahmic\ApiSutra\Exceptions\Transport\ConnectionException;
use GuzzleHttp\Psr7\Request;
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

it('нормализует PSR network failure без зависимости от Guzzle HTTP Client', function (): void {
    $failure = new PsrNetworkFailure(new Request('GET', 'https://api.test'));
    $client = new SequenceHttpClient([$failure, $failure]);
    $factory = new HttpFactory();
    $transport = new HttpTransport($client, $factory, $factory);
    $prepared = new PreparedRequest(HttpMethod::GET, 'https://api.test');
    foreach ([false, true] as $async) {
        try {
            $async ? $transport->sendAsync($prepared)->wait() : $transport->send($prepared);
            test()->fail('Ожидалась ошибка транспорта');
        } catch (ConnectionException $exception) {
            expect($exception->getPrevious())->toBe($failure);
        }
    }
    expect($client->calls)->toBe(2);
});
