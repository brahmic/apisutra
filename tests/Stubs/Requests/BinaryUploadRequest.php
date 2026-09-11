<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Http\FileFormat;
use Brahmic\ApiSutra\VO\Files\FileInput;

#[Post('/upload/binary')]
final class BinaryUploadRequest extends AbstractRequest
{
    public function __construct(
        #[File(format: FileFormat::Binary)]
        public FileInput $file,
    ) {}
}
