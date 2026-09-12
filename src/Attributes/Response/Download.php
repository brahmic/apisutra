<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes\Response;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Download
{
    public function __construct()
    {
    }
}
