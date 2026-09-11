<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Laravel\ClientResponseAdapter;
use Brahmic\ApiSutra\Response\ClientResponse;
use Brahmic\ApiSutra\VO\Files\FileResponse;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

describe('ClientResponseAdapter (Laravel)', function () {
    it('возвращает JsonResponse для массива', function () {
        $adapter = new ClientResponseAdapter();
        $clientResponse = new ClientResponse(
            status: 201,
            headers: ['X-Test' => 'ok'],
            body: ['ok' => true],
        );

        $response = $adapter->toResponse($clientResponse);

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(201)
            ->and($response->headers->get('X-Test'))->toBe('ok');
        $payload = json_decode((string) $response->getContent(), true);
        expect($payload)->toBe(['ok' => true]);
    });

    it('возвращает Response для строки', function () {
        $adapter = new ClientResponseAdapter();
        $clientResponse = new ClientResponse(
            status: 202,
            headers: ['X-Mode' => 'text'],
            body: 'hello',
        );

        $response = $adapter->toResponse($clientResponse);

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(202)
            ->and($response->headers->get('X-Mode'))->toBe('text')
            ->and($response->getContent())->toBe('hello');
    });

    it('возвращает StreamedResponse для FileResponse', function () {
        $adapter = new ClientResponseAdapter();
        $stream = Utils::streamFor('file-content');
        $file = new FileResponse(
            stream: $stream,
            filename: 'report.txt',
            mimeType: 'text/plain',
            size: 12,
        );

        $clientResponse = new ClientResponse(
            status: 200,
            headers: ['X-Test' => 'file'],
            body: $file,
        );

        $response = $adapter->toResponse($clientResponse);

        expect($response)->toBeInstanceOf(StreamedResponse::class)
            ->and($response->getStatusCode())->toBe(200)
            ->and($response->headers->get('X-Test'))->toBe('file')
            ->and($response->headers->get('Content-Type'))->toBe('text/plain');
        $disposition = $response->headers->get('Content-Disposition');
        expect($disposition)->not->toBeNull()
            ->and(str_contains((string)$disposition, 'report.txt'))->toBeTrue()
            ->and($response->headers->get('Content-Length'))->toBe('12');
    });
});
