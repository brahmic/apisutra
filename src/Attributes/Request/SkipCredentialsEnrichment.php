<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes\Request;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SkipCredentialsEnrichment
{
}
