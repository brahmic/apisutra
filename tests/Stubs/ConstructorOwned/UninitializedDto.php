<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ConstructorOwned;

final class UninitializedDto
{
    public string $value;

    public function __construct()
    {
        State::$calls++;
    }
}
