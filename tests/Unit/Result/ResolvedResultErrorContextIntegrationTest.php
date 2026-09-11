<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Response\ClientResponse;
use Brahmic\ApiSutra\Response\ClientResponseFactoryInterface;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;
use Brahmic\ApiSutra\Tests\Stubs\Core\TestClientErrorMapper;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Errors\ClientError;
use Brahmic\ApiSutra\VO\Errors\ErrorContextFactoryInterface;

function makeIntegrationErrorContextFactory(): ErrorContextFactoryInterface
{
    return new class implements ErrorContextFactoryInterface
    {
        public function make(ClientError $error): ?object
        {
            return (object) ['code' => $error->sdkCode->value];
        }
    };
}

function makeIntegrationResponseFactory(): ClientResponseFactoryInterface
{
    return new class implements ClientResponseFactoryInterface
    {
        public function make(ResolvedResultInterface $result): ClientResponse
        {
            return new ClientResponse(status: 200, headers: [], body: 'ok');
        }
    };
}

describe('ResolvedResult error context integration', function () {
    it('прокидывает errorContextFactory из ClientConfig', function () {
        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => [
                'status' => 500,
                'body' => '{"message":"fail"}',
                'headers' => [],
            ],
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
                errorMapper: new TestClientErrorMapper(),
                errorContextFactory: makeIntegrationErrorContextFactory(),
                responseFactory: makeIntegrationResponseFactory(),
            ),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);

        $context = $request->send()->resolved()->errorContext();

        expect($context?->code)->toBe('connection_failed');
    });
});
