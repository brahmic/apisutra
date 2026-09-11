<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\ControlFlow;

use Exception;

class RetryableException extends ControlFlowException
{
    public function __construct(
        string $message = 'Требуется повтор запроса',
        public readonly ?int $retryAfter = null,
        public readonly ?int $maxAttempts = null,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
