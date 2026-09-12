<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\ValidationProbe;

use Brahmic\ApiSutra\Attributes\DataTransfer\Validate;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class ProbeDto extends AbstractDto
{
    public function __construct(
        #[Validate('fixture_rule')]
        public string $value = 'fixture-value',
    ) {}
}
