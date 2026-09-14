<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;

final readonly class DiscriminatedDto
{
    /** @param list<MappedDto|array<string, mixed>> $items */
    public function __construct(
        #[Nested(discriminator: 'kind', map: ['known' => MappedDto::class])] public array $items,
    ) {
    }
}
