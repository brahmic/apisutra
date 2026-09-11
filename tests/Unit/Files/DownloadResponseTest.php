<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use Brahmic\ApiSutra\Tests\Support\TestClientFactory;
use Brahmic\ApiSutra\VO\Files\FileResponse;

describe('Download response', function () {
    it('возвращает FileResponse для download-запроса', function () {
        $client = TestClientFactory::make([
            ProviderBDownloadRequest::class => MockResponse::make(
                'binary-content',
                200,
                ['Content-Type' => 'application/octet-stream'],
            ),
        ]);

        $request = new ProviderBDownloadRequest('op-1');
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->status)->toBe(ResultStatus::SUCCESS);
        expect($result->data)->toBeInstanceOf(FileResponse::class);
        expect($result->data->mimeType())->toBe('application/octet-stream');
        expect($result->data->size())->toBe(strlen('binary-content'));
    });
});
