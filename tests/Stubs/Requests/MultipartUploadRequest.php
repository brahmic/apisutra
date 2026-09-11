<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Http\FileFormat;
use Brahmic\ApiSutra\VO\Files\FileInput;

#[Post('/upload/multipart')]
final class MultipartUploadRequest extends AbstractRequest
{
    /**
     * @param array<int, FileInput> $files Массив файлов
     */
    public function __construct(
        #[File(name: 'files', format: FileFormat::Multipart)]
        public array $files,
        #[Body]
        public string $comment,
    ) {}
}
