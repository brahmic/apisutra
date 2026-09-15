<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/configuration-probe')]
final class ProbeRequest extends AbstractRequest {}
