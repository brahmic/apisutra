<?php

declare(strict_types=1);

final readonly class RenameOnly
{
    public function __construct(public int $recordId, public array $extra = []) {}
}
