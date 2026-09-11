<?php

declare(strict_types=1);

namespace Acme\Discovery\Resources;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/resource')]
final class DiscoveryResourceRequest extends AbstractRequest
{
}
