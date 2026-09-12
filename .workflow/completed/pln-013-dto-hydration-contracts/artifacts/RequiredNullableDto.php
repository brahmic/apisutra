<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\HydrationProbe;

use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class RequiredNullableDto extends AbstractDto
{
    public function __construct(public ?string $note) {}
}
