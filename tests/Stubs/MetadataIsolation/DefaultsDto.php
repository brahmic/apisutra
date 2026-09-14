<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class DefaultsDto extends AbstractDto
{
    /** @param list<MutableCounter> $nested */
    public function __construct(
        public string $value = 'default',
        public MutableCounter $state = new MutableCounter(),
        public array $nested = [new MutableCounter()],
    ) {
    }
}
