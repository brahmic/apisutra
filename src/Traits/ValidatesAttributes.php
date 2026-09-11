<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Traits;

use Brahmic\ApiSutra\Exceptions\Validation\ValidationException;
use Brahmic\ApiSutra\VO\Errors\ValidationError;
use Brahmic\ApiSutra\VO\Validation\Validator;

trait ValidatesAttributes
{
    #[\Override]
    public function validate(): static
    {
        $result = Validator::check($this);
        if ($result->failed()) {
            throw new ValidationException($result->errors());
        }

        return $this;
    }

    #[\Override]
    public function isValid(): bool
    {
        return Validator::check($this)->passed();
    }

    /**
     * @return array<ValidationError>
     */
    #[\Override]
    public function errors(): array
    {
        return Validator::check($this)->errors();
    }
}
