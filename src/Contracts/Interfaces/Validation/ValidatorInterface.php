<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Validation;

use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ValidationResult;

interface ValidatorInterface
{
    /**
     * Валидировать объект и вернуть результат
     */
    public static function check(object $value): ValidationResult;
}
