<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Validation;

use Brahmic\ApiSutra\Attributes\DataTransfer\Validate;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/validation-probe')]
final class ScopedValidationRequest extends AbstractRequest
{
    public function __construct(
        #[Validate('fixture_rule')]
        public string $value = 'fixture-value',
    ) {}
}
