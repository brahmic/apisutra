<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Profiles;

use Brahmic\ApiSutra\Config\DtoHydrationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;
use Brahmic\ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class UppercaseHydrationProfile implements DtoHydrationProfileInterface
{
    #[\Override]
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy();
    }

    public function casts(): array
    {
        return [
            'string' => new UppercaseCast(),
        ];
    }
}
