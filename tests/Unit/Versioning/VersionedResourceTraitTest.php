<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Exceptions\Configuration\UnsupportedVersionException;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Stubs\Versioning\Enums\BackedApiVersion;
use Brahmic\ApiSutra\Tests\Stubs\Versioning\Enums\NamedApiVersion;
use Brahmic\ApiSutra\Tests\Stubs\Versioning\Requests\VersionedRequestV3;
use Brahmic\ApiSutra\Tests\Stubs\Versioning\Resources\VersionedChildV3Resource;
use Brahmic\ApiSutra\Tests\Stubs\Versioning\Resources\VersionedRootResource;
use Brahmic\ApiSutra\Transport\MockTransport;

function makeVersionedClient(): TestClient
{
    return new TestClient(
        new ClientConfig(baseUrl: 'https://example.test'),
        new MockTransport(),
    );
}

describe('VersionedResourceTrait', function () {
    it('переключает версию по BackedEnum', function () {
        $resource = new VersionedRootResource(makeVersionedClient(), 'v2');

        $next = $resource->v(BackedApiVersion::V3);

        expect($next)->toBeInstanceOf(VersionedRootResource::class);
        expect($next->versionKey())->toBe('v3');
    });

    it('переключает версию по UnitEnum имени', function () {
        $resource = new VersionedRootResource(makeVersionedClient(), 'v2');

        $next = $resource->v(NamedApiVersion::V3);

        expect($next)->toBeInstanceOf(VersionedRootResource::class);
        expect($next->versionKey())->toBe('V3');
    });

    it('роутит вложенный ресурс по версии', function () {
        $resource = new VersionedRootResource(makeVersionedClient(), 'v3');

        $child = $resource->resolveChild('child-token');

        expect($child)->toBeInstanceOf(VersionedChildV3Resource::class);
        expect($child->token)->toBe('child-token');
    });

    it('роутит запрос по версии и привязывает клиента', function () {
        $client = makeVersionedClient();
        $resource = new VersionedRootResource($client, 'v3');

        $request = $resource->resolveRequest('abc');

        expect($request)->toBeInstanceOf(VersionedRequestV3::class);
        expect($request->query)->toBe('abc');
        expect($request->getClient())->toBe($client);
    });

    it('бросает UnsupportedVersionException для неподдержанной версии', function () {
        $resource = new VersionedRootResource(makeVersionedClient(), 'v9');

        expect(fn () => $resource->resolveChild('x'))
            ->toThrow(UnsupportedVersionException::class);
    });
});

