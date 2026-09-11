<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Profiles;

use Brahmic\ApiSutra\Config\DtoHydrationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;
use Brahmic\ApiSutra\Enums\Configuration\NamingStrategy;

final readonly class SnakeCaseHydrationProfile implements DtoHydrationProfileInterface
{
    #[\Override]
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy(
            namingStrategy: NamingStrategy::SnakeCase,
        );
    }

    public function casts(): array
    {
        return [];
    }
}
