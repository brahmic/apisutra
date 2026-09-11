<?php

declare(strict_types=1);

namespace Acme\Discovery\Requests;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/discovery')]
final class DiscoveryRequest extends AbstractRequest
{
}
