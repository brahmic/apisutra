<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class OutputUserDto extends AbstractDto
{
    /**
     * @param array<int, OutputItemDto> $items
     */
    public function __construct(
        #[To('user_id')]
        public int $userId,
        #[To('profile')]
        public OutputAddressDto $address,
        #[To('items')]
        public array $items,
        #[To('title')]
        #[Cast(UppercaseCast::class)]
        public string $title,
        #[To('meta.tags')]
        public array $tags = [],
        public ?string $plainValue = null,
    ) {}
}
