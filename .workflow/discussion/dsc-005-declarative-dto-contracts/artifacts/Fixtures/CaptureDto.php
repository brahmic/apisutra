<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Dsc005\Fixtures;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;

final readonly class CaptureDto
{
    /** @param array<string, mixed> $source */
    public function __construct(
        #[DefaultValue(provider: CaptureHandler::class)]
        public array $source,
        #[Cast(CaptureHandler::class)]
        public string $probe = 'ok',
    ) {
    }
}
