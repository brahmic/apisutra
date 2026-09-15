<?php

declare(strict_types=1);

final readonly class OwnedEnvelope
{
    /** @param list<ConstructorOwned> $items */
    public function __construct(public array $items) {}
}
