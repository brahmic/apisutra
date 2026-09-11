<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Validation;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\VO\Errors\ValidationError;
use Exception;

class ValidationException extends SdkException
{
    /**
     * @param array<ValidationError> $errors
     */
    public function __construct(
        public readonly array $errors,
        string $message = 'Ошибка валидации',
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
