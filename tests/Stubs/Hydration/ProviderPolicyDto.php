<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hydration;

use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\DtoHydrate;
use Brahmic\ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;

#[DtoHydrate(emptyStringBehavior: EmptyStringBehavior::NullIfBlank)]
final readonly class ProviderPolicyDto
{
    public function __construct(
        #[DefaultValue(provider: StateProvider::class, when: [ValueState::Null, ValueState::Present])]
        public ?string $value = null,
    ) {
    }
}
