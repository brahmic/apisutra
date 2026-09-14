<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\MetadataIsolation;

final readonly class FilledDefaultDto
{
    public string $label;

    public function __construct(public ?CreatedValue $state = new CreatedValue())
    {
    }
}
