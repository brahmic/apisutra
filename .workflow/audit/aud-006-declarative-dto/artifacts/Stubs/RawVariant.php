<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

final readonly class RawVariant
{
    /** @param array<string, mixed> $payload */
    public function __construct(public array $payload)
    {
    }
}
