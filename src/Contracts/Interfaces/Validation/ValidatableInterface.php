<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Validation;

use Brahmic\ApiSutra\VO\Errors\ValidationError;

interface ValidatableInterface
{
    /**
     * Валидировать по правилам #[Validate]
     */
    public function validate(): static;

    /**
     * Проверить валидность без exception
     */
    public function isValid(): bool;

    /**
     * Получить ошибки валидации
     * @return array<ValidationError>
     */
    public function errors(): array;
}
