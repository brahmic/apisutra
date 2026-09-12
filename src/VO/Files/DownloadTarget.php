<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\VO\Files;

use Psr\Http\Message\StreamInterface;

final readonly class DownloadTarget
{
    public function __construct(
        public string|StreamInterface $target,
        public bool $overwrite = false,
    ) {
    }
}
