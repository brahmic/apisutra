<?php

declare(strict_types=1);

namespace Example\Files\Resources\Files;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Response\Download;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/files/{id}')]
#[Download]
final class DownloadFileRequest extends AbstractRequest
{
    public function __construct(#[Path] public int $id)
    {
    }
}
