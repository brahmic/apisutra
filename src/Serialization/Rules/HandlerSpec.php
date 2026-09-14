<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\Rules;

final readonly class HandlerSpec
{
    /** @param array<int|string, mixed> $args */
    public function __construct(public string $class, public array $args = [])
    {
        LiteralValues::assert($args);
    }
}
