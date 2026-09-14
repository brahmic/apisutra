<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class ProviderStatesDto
{
    public function __construct(
        #[DefaultValue(provider: StateProvider::class, when: [ValueState::Null, ValueState::Present])]
        public ?string $keep = null,
        #[EmptyStringAsNull]
        #[DefaultValue(provider: StateProvider::class, when: [ValueState::Null, ValueState::Present])]
        public ?string $normalized = null,
        #[Cast(UppercaseCast::class)]
        #[EmptyStringAsNull]
        #[DefaultValue(provider: StateProvider::class, when: [ValueState::Null, ValueState::Present])]
        public ?string $cast = null,
    ) {
    }
}
