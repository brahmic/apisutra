<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Transport;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\Enums\Http\TransmissionState;
use Throwable;

class TransportException extends SdkException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly TransmissionState $transmissionState = TransmissionState::Unknown,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
