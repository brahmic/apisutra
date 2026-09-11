<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Versioning\Resources;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Core\AbstractResource;
use Brahmic\ApiSutra\Tests\Stubs\Versioning\Requests\VersionedRequestV2;
use Brahmic\ApiSutra\Tests\Stubs\Versioning\Requests\VersionedRequestV3;
use Brahmic\ApiSutra\Versioning\VersionedResourceTrait;

final class VersionedRootResource extends AbstractResource
{
    use VersionedResourceTrait;

    public function __construct(
        ClientInterface $client,
        private readonly string $version = 'v2',
    ) {
        parent::__construct($client);
    }

    public function versionKey(): string
    {
        return $this->version;
    }

    public function resolveChild(string $token): AbstractResource
    {
        return $this->resourceByVersion([
            'v2' => VersionedChildV2Resource::class,
            'v3' => VersionedChildV3Resource::class,
        ], $token);
    }

    public function resolveRequest(string $query): AbstractRequest
    {
        return $this->requestByVersion([
            'v2' => VersionedRequestV2::class,
            'v3' => VersionedRequestV3::class,
        ], $query);
    }

    #[\Override]
    protected function currentVersionKey(): string
    {
        return $this->version;
    }

    #[\Override]
    protected function recreateWithVersion(string $version): static
    {
        return new self($this->client, $version);
    }
}
