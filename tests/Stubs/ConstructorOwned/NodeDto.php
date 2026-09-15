<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ConstructorOwned;

final class NodeDto
{
    public string $value;

    public function __construct(public int $id, public array $_extra = [])
    {
        State::$calls++;
        $this->value = 'known';
    }
}
