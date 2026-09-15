<?php

declare(strict_types=1);

namespace Example\Records\AttributeExample;

use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;

final readonly class RecordDto extends AbstractResponseDto
{
    public function __construct(
        #[From('record_id')]
        public int $id,
        public string $title,
    ) {
    }
}
