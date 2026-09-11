<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\Tests\Stubs\Enums\TitleStatus;

final readonly class ProfileDrivenOutputDto extends ProfileDrivenBaseDto
{
    public function __construct(
        #[To('status')]
        public TitleStatus $status,
        public ?string $plainValue = null,
    ) {}
}
