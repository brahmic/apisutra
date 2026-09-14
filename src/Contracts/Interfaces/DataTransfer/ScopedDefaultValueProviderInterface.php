<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer;

use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\Serialization\Rules\HydrationScope;

interface ScopedDefaultValueProviderInterface extends DefaultValueProviderInterface
{
    /** @param array<string, mixed> $source */
    public function resolveInScope(mixed $value, ValueState $state, array $source, HydrationScope $scope): mixed;
}
