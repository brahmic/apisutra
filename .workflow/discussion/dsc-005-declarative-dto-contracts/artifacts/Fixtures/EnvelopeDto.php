<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Dsc005\Fixtures;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;

final readonly class EnvelopeDto
{
    /** @param list<CaptureDto> $each @param list<CaptureDto> $value @param list<CaptureDto> $key */
    public function __construct(
        #[Nested(type: CaptureDto::class, each: 'value')]
        public array $each,
        #[Nested(discriminator: 'type', map: ['known' => CaptureDto::class])]
        public array $value,
        #[Nested(map: ['known' => CaptureDto::class], discriminatorMode: NestedDiscriminatorMode::Key)]
        public array $key,
    ) {
    }
}
