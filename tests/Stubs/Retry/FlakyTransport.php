<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Retry;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use RuntimeException;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Promise\PromiseInterface;

final class FlakyTransport implements TransportInterface
{
    public int $calls = 0;

    public function send(PreparedRequest $request): ProviderResponse
    {
        $this->calls++;
        if ($this->calls === 1) {
            throw new FlakyTransportException('fail');
        }

        return new ProviderResponse(
            status: 200,
            headers: ['Content-Type' => ['application/json']],
            body: '{}',
            request: $request,
            duration: 0,
        );
    }

    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        throw new RuntimeException('Not implemented for tests.');
    }
}
