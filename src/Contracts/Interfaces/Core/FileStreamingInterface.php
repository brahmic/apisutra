<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Core;

use Brahmic\ApiSutra\VO\Files\FileTransferOptions;

/** Гарантия передачи файлов без полной материализации и закрытия чужих потоков. */
interface FileStreamingInterface
{
    public function assertSupportsFileTransfer(FileTransferOptions $options): void;
}
