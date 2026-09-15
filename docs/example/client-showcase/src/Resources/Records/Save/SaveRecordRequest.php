<?php

declare(strict_types=1);

namespace Example\ClientShowcase\Resources\Records\Save;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Example\ClientShowcase\Resources\Records\RecordDto;

#[Post('/records')]
final class SaveRecordRequest extends AbstractRequest
{
    public function __construct(#[BodyRoot] public RecordDto $record)
    {
    }
}
