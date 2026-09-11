<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Http\FileFormat;
use Brahmic\ApiSutra\VO\Files\FileInput;

#[Post('/upload/base64')]
final class Base64UploadRequest extends AbstractRequest
{
    public function __construct(
        #[File(name: 'file', format: FileFormat::Base64)]
        public FileInput $file,
        #[Body]
        public string $note,
    ) {}
}
