<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Support;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Promise\PromiseInterface;

final class FakeTransport implements TransportInterface
{
    public int $sendCalls = 0;
    public ?ProviderResponse $nextResponse = null;

    public function send(PreparedRequest $request): ProviderResponse
    {
        $this->sendCalls++;

        if ($this->nextResponse instanceof ProviderResponse) {
            return $this->nextResponse;
        }

        return new ProviderResponse(
            status: 200,
            headers: [],
            body: '',
            request: $request,
            duration: 0,
        );
    }

    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        throw new \RuntimeException('Not implemented for tests.');
    }
}
