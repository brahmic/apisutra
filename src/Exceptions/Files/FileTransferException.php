<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Files;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Throwable;

final class FileTransferException extends SdkException
{
    public function __construct(
        public readonly string $stage,
        public readonly int $bytesWritten = 0,
        public readonly bool $partial = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Ошибка файловой передачи: ' . $stage, 0, $previous);
    }
}
