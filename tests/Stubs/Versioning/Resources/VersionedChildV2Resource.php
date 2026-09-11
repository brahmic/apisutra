<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Versioning\Resources;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Core\AbstractResource;

final class VersionedChildV2Resource extends AbstractResource
{
    public function __construct(
        ClientInterface $client,
        public string $token = 'v2',
    ) {
        parent::__construct($client);
    }
}
