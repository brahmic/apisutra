<?php

declare(strict_types=1);

namespace Example\Records;

use Brahmic\ApiSutra\Core\AbstractClient;
use Example\Records\Resources\Records\RecordsResource;

final class DemoClient extends AbstractClient
{
    public function records(): RecordsResource
    {
        return new RecordsResource($this);
    }
}
