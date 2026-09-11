<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\CatalogMega\ServiceA\Resources\Files\Requests\DownloadFile;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Download;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/mega/serviceA/files/download')]
#[Download]
final class MegaServiceADownloadFileRequest extends AbstractRequest
{
}
