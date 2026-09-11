<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Core;

use Brahmic\ApiSutra\Response\ClientResponse;
use Brahmic\ApiSutra\Response\ClientResponseFactoryInterface;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;

final readonly class TestClientResponseFactory implements ClientResponseFactoryInterface
{
    public function make(ResolvedResultInterface $result): ClientResponse
    {
        return new ClientResponse(
            status: 201,
            headers: ['X-Test' => 'ok'],
            body: ['ok' => true],
        );
    }
}
