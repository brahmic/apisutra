<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Catalog\Resources\Files\Requests\DownloadAttachment;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Download;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/catalog/files/attachment')]
#[Download]
final class CatalogDownloadAttachmentRequest extends AbstractRequest
{
}
