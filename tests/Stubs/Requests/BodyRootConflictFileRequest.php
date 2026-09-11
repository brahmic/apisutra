<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Http\Patch;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\VO\Files\FileInput;

#[Patch('/body-root/conflict/file')]
final class BodyRootConflictFileRequest extends AbstractRequest
{
    /**
     * @param array<int, array<string, mixed>> $operations
     */
    public function __construct(
        #[BodyRoot]
        public array $operations,
        #[File('file')]
        public FileInput $file,
    ) {}
}
