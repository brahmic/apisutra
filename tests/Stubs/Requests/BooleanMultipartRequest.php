<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Enums\Http\FileFormat;
use Brahmic\ApiSutra\VO\Files\FileInput;

#[Post('/booleans/multipart')]
final class BooleanMultipartRequest extends BooleanWireRequest
{
    /** @var list<FileInput> */
    #[File(format: FileFormat::Multipart)]
    public array $files = [];
}
