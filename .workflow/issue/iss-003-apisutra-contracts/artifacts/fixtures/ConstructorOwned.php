<?php

declare(strict_types=1);

final readonly class ConstructorOwned
{
    public string $kind;

    public function __construct(public int $id, public array $extra = [])
    {
        $this->kind = 'known';
    }
}
