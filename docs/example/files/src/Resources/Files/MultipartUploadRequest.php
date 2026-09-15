<?php

declare(strict_types=1);

namespace Example\Files\Resources\Files;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Http\FileFormat;
use Brahmic\ApiSutra\VO\Files\FileInput;

#[Post('/files')]
final class MultipartUploadRequest extends AbstractRequest
{
    public function __construct(
        #[File('document', format: FileFormat::Multipart)]
        public FileInput $document,
        #[Body('description')]
        public string $description,
    ) {
    }
}
