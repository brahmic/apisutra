<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Validation;

use Brahmic\ApiSutra\Attributes\DataTransfer\Validate;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class ScopedValidationDto extends AbstractDto
{
    public function __construct(
        #[Validate('fixture_rule')]
        public string $value = 'fixture-value',
    ) {}
}
