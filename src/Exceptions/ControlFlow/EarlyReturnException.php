<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\ControlFlow;

use Exception;

class EarlyReturnException extends ControlFlowException
{
    public function __construct(
        public readonly mixed $data,
        string $message = 'Возврат без HTTP',
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
