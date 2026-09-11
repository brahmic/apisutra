<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Extensions\Archive\Handlers\ArchiveResponseHandler;
use Brahmic\ApiSutra\Extensions\Archive\Response\ArchiveResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('ArchiveResponseHandler', function () {
    it('распознаёт архив по content-type и формирует ArchiveResponse', function () {
        $handler = new ArchiveResponseHandler();
        $response = new ProviderResponse(
            status: 200,
            headers: ['Content-Type' => ['application/zip']],
            body: 'PK' . 'content',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test/archive'),
            duration: 0,
        );

        expect($handler->supports($response))->toBeTrue();

        $context = new PipelineContext(
            request: new SimpleGetRequest('payload'),
            config: new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            traceId: 'trace',
            role: RequestRole::Root,
        );
        $archive = $handler->handle($response, $context);

        expect($archive)->toBeInstanceOf(ArchiveResponse::class)
            ->and($archive->getFormat())->toBe('zip');
    });

    it('распознаёт gzip по magic-bytes', function () {
        $handler = new ArchiveResponseHandler();
        $response = new ProviderResponse(
            status: 200,
            headers: [],
            body: "\x1F\x8B" . 'data',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test/archive'),
            duration: 0,
        );

        expect($handler->supports($response))->toBeTrue();
    });

    it('определяет формат tar.gz по content-type', function () {
        $handler = new ArchiveResponseHandler();
        $response = new ProviderResponse(
            status: 200,
            headers: ['Content-Type' => ['application/gzip']],
            body: "\x1F\x8B" . 'data',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test/archive'),
            duration: 0,
        );
        $context = new PipelineContext(
            request: new SimpleGetRequest('payload'),
            config: new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $archive = $handler->handle($response, $context);

        expect($archive)->toBeInstanceOf(ArchiveResponse::class)
            ->and($archive->getFormat())->toBe('tar.gz');
    });
});
