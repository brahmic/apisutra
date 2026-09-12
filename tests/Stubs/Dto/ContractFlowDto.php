<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class ContractFlowDto extends AbstractDto
{
    public function __construct(
        #[From('is_active')] public bool $active,
        public int $count,
        public ?string $note,
        public PolymorphicOwnersArrayKeyKeepRawDto $group,
        public string $missing = 'default',
    ) {
    }
}
