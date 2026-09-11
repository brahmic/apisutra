<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer;

use Brahmic\ApiSutra\VO\Errors\ValidationError;

interface ValidationResult
{
    public function passed(): bool;
    public function failed(): bool;

    /**
     * @return array<ValidationError>
     */
    public function errors(): array;
}
