<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes\Request;

use Attribute;
use BackedEnum;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class AuthScope
{
    /**
     * @param string|BackedEnum $scope Ключ scope (строка или string-backed enum case).
     */
    public string $scope;

    public function __construct(string|BackedEnum $scope)
    {
        $resolved = $scope instanceof BackedEnum ? $scope->value : $scope;

        if (!is_string($resolved)) {
            throw new InvalidArgumentException('AuthScope поддерживает только string-backed enum или строку');
        }

        $this->scope = $resolved;
    }
}
