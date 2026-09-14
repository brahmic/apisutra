<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\VO\Files\FileInput;

#[Post('/wire')]
final class MultipartRequest extends AbstractRequest
{
    public function __construct(#[Body] public mixed $payload, #[File] public FileInput $file)
    {
    }
}
