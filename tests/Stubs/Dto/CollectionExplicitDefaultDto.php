<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\Tests\Stubs\Collections\OutputItemCollection;

final readonly class CollectionExplicitDefaultDto extends AbstractDto
{
    public function __construct(
        #[DefaultValue(value: [['id' => 99, 'label' => 'Default']], when: [ValueState::Missing])]
        #[Nested(type: OutputItemDto::class, from: 'items')]
        public OutputItemCollection $items,
    ) {}
}
