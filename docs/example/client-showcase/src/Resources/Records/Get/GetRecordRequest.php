<?php

declare(strict_types=1);

namespace Example\ClientShowcase\Resources\Records\Get;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Example\ClientShowcase\Resources\Records\RecordDto;

// Повторы и кеш задаются клиентом: у операции нет собственных переопределений.
#[Get('/records/{id}')]
#[Returns(RecordDto::class, unwrap: 'data')]
final class GetRecordRequest extends AbstractRequest
{
    public function __construct(#[Path] public int $id)
    {
    }
}
