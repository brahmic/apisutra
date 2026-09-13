<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Serialization;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Throwable;

class ResponseDecodingException extends SdkException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
