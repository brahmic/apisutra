<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Validation;

use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\VO\Errors\ValidationError;

interface ValidatableInterface
{
    /**
     * Валидировать по правилам #[Validate].
     * @throws ConfigurationException Если объявленные проверки недоступны.
     */
    public function validate(): static;

    /**
     * Вернуть результат проверки данных; недоступность проверок является ошибкой настройки.
     * @throws ConfigurationException Если объявленные проверки недоступны.
     */
    public function isValid(): bool;

    /**
     * Получить ошибки валидации
     * @return array<ValidationError>
     * @throws ConfigurationException Если объявленные проверки недоступны.
     */
    public function errors(): array;
}
