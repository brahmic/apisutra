<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Resources;

use Brahmic\ApiSutra\Core\AbstractResource;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;

final class TestResource extends AbstractResource
{
    public function makeRequest(string $query): SimpleGetRequest
    {
        return $this->request(SimpleGetRequest::class, $query);
    }
}
