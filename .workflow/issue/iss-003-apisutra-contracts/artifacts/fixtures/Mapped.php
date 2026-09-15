<?php

declare(strict_types=1);

final readonly class Mapped
{
    public function __construct(public int $id, public ?int $count = null, public array $extra = []) {}
}
