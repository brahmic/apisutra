<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

final readonly class StrictScalarDto extends StrictBase
{
    public function __construct(public ?int $count = null, public ?bool $enabled = null, public ?string $label = null)
    {
    }
}
