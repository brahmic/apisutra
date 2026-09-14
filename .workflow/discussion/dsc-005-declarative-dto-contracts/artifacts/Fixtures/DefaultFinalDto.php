<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Dsc005\Fixtures;

use stdClass;

final readonly class DefaultFinalDto
{
    public function __construct(
        public string $value = 'default',
        public stdClass $metadata = new stdClass(),
    ) {
    }
}
