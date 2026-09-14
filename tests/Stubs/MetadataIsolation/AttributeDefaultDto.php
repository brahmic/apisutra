<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;

final readonly class AttributeDefaultDto
{
    /** @param list<MutableCounter> $states */
    public function __construct(
        #[DefaultValue(value: [new MutableCounter()])]
        public array $states,
    ) {
    }
}
