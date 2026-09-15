<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records;

use Brahmic\ApiSutra\Core\AbstractResource;
use Example\Records\Resources\Records\Get\GetRecordRequest;

final class RecordsResource extends AbstractResource
{
    public function get(int $id): GetRecordRequest
    {
        return (new GetRecordRequest($id))->setClient($this->client);
    }
}
