<?php

declare(strict_types=1);

final readonly class Rows
{
    public function __construct(public array $rows, public array $extra = []) {}
}
