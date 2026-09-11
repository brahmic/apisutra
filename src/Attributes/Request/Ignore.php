<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes\Request;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Ignore
{
    public function __construct() {}
}
