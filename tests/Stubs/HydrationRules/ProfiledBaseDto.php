<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

#[DtoHydrationProfile(ScopedProfile::class)]
abstract readonly class ProfiledBaseDto extends AbstractDto
{
}
