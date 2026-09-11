<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Tests\Stubs\Requests\OperationDescriptorRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\OverrideEndpointRequest;
use Brahmic\ApiSutra\Tests\Stubs\Resources\TestResource;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Request core', function () {
    it('использует resolveEndpoint/resolveBaseUrl вместо декларации', function () {
        $request = new OverrideEndpointRequest();

        expect($request->getEndpoint())->toBe('/runtime');
        expect($request->getBaseUrl())->toBe('https://runtime.test');
    });

    it('AbstractResource::request привязывает клиент к запросу', function () {
        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            new MockTransport(),
        );

        $resource = new TestResource($client);
        $request = $resource->makeRequest('q');

        expect($request->getClient())->toBe($client);
    });

    it('отдает OperationDescriptor accessors из RequestSpec', function () {
        $request = new OperationDescriptorRequest();

        expect($request->getOperationDescriptorAttribute())->not->toBeNull()
            ->and($request->getOperationTitle())->toBe('Operation title')
            ->and($request->getOperationDescription())->toBe('Operation description')
            ->and($request->getOperationNote())->toBe('Operation note');
    });
});
