<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\DtoSerialize;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\Enums\Serialization\EnumOutput;
use Brahmic\ApiSutra\Tests\Stubs\Enums\TitleStatus;

#[DtoSerialize(enumOutput: EnumOutput::Object, serializeNulls: false)]
final readonly class ProfileDrivenOverrideDto extends ProfileDrivenBaseDto
{
    public function __construct(
        #[To('status')]
        public TitleStatus $status,
        public ?string $plainValue = null,
    ) {}
}
