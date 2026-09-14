<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Config\DtoHydrationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Serialization\DtoHydrationProfileInterface;

final readonly class StrictProfile implements DtoHydrationProfileInterface
{
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy();
    }

    /** @return array<string, CastInterface> */
    public function casts(): array
    {
        return [
            'int' => new StrictScalarCast('int'),
            'bool' => new StrictScalarCast('bool'),
            'string' => new StrictScalarCast('string'),
        ];
    }
}
