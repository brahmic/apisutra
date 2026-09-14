<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;

final readonly class ScopedAttributeDto
{
    public function __construct(
        #[Cast(ScopedChildCast::class)] public RecordDto $child,
        #[Nested(itemCast: ScopedRowCast::class)] public array $rows,
        #[DefaultValue(provider: ScopedChildProvider::class, when: [ValueState::Present])] public RecordDto $provided,
    ) {
    }
}
