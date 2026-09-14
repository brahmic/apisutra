<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrationProfile;
use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;

#[DtoHydrationProfile(StrictProfile::class)]
abstract readonly class StrictBase extends AbstractResponseDto
{
}
