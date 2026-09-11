<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes\Behavior;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class NoAuth
{
    public function __construct() {}
}
