<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Support;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Promise\PromiseInterface;
use LogicException;

final class UnsupportedTimeoutTransport implements TransportInterface
{
    public function send(PreparedRequest $request): ProviderResponse { throw new LogicException('HTTP не должен начаться'); }
    public function sendAsync(PreparedRequest $request): PromiseInterface { throw new LogicException('HTTP не должен начаться'); }
}
