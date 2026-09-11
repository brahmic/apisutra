<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoSerializationProfile;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Profiles\ProfileDrivenDtoSerializationProfile;

#[DtoSerializationProfile(ProfileDrivenDtoSerializationProfile::class)]
abstract readonly class ProfileDrivenBaseDto extends AbstractDto {}
