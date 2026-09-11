<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Casts\EnumCast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Enums\TestStatus;

final readonly class ComplexDto extends AbstractDto
{
    /**
     * @param array<int, NestedItemDto> $items
     * @param array<int, string> $tags
     */
    public function __construct(
        #[From('status')]
        #[Cast(EnumCast::class, TestStatus::class)]
        public ?TestStatus $status = null,
        #[From('items')]
        #[Nested(type: NestedItemDto::class)]
        public array $items = [],
        #[From('meta.inner.value')]
        public ?string $innerValue = null,
        #[From('tags')]
        #[Nested(each: 'value')]
        public array $tags = [],
    ) {}
}
