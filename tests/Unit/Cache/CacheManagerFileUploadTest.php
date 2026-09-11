<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\Base64UploadRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\BinaryUploadRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\MultipartUploadRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\SpyCache;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Files\FileInput;

describe('CacheManager file uploads', function () {
    it('не кеширует multipart upload', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            MultipartUploadRequest::class => MockResponse::sequence([
                MockResponse::success(['value' => 1]),
                MockResponse::success(['value' => 2]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $file = FileInput::fromContent('data', 'test.txt');
        $request = new MultipartUploadRequest([$file], 'note');
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $second = $request->withCache()->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 2])
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($cache->lastSetKey)->toBeNull();
    });

    it('не кеширует base64 upload', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            Base64UploadRequest::class => MockResponse::sequence([
                MockResponse::success(['value' => 1]),
                MockResponse::success(['value' => 2]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $file = FileInput::fromContent('data', 'test.txt');
        $request = new Base64UploadRequest($file, 'note');
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $second = $request->withCache()->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 2])
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($cache->lastSetKey)->toBeNull();
    });

    it('не кеширует binary upload', function () {
        $cache = new SpyCache();
        $transport = new MockTransport();
        $transport->fake([
            BinaryUploadRequest::class => MockResponse::sequence([
                MockResponse::success(['value' => 1]),
                MockResponse::success(['value' => 2]),
            ]),
        ]);

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            cache: new CacheConfig(store: $cache, ttl: 60, prefix: 'test-account'),
            environment: Environment::Testing,
        );

        $client = new TestClient($config, $transport);
        $file = FileInput::fromContent('data', 'test.txt');
        $request = new BinaryUploadRequest($file);
        $request->setClient($client);

        $first = $request->withCache()->send()->raw();
        $second = $request->withCache()->send()->raw();

        expect($first->data)->toBe(['value' => 1])
            ->and($second->data)->toBe(['value' => 2])
            ->and($transport->getRecorded())->toHaveCount(2)
            ->and($cache->lastSetKey)->toBeNull();
    });
});
