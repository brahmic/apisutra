<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Versioning\Requests;

use Brahmic\ApiSutra\Core\AbstractRequest;

final class VersionedRequestV3 extends AbstractRequest
{
    public function __construct(
        public string $query = 'v3',
    ) {}
}
