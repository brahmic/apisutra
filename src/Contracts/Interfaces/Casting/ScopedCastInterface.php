<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Casting;

use Brahmic\ApiSutra\Serialization\Rules\HydrationScope;

interface ScopedCastInterface extends CastInterface
{
    public function hydrateInScope(mixed $value, HydrationScope $scope): mixed;
}
