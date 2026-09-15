<?php

declare(strict_types=1);

namespace Example\ClientShowcase\Resources\Records;

use Brahmic\ApiSutra\DataTransfer\AbstractDto;

// Общая модель чтения и сохранения записи в этом вымышленном API.
final readonly class RecordDto extends AbstractDto
{
    /** @param array<string, mixed> $_extra */
    public function __construct(
        public int $id,
        public string $title,
        public array $_extra = [],
    ) {
    }
}
