<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Execution\SendMode;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Request\RequestExecution;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Brahmic\ApiSutra\Resolver\ClientResolver;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;
use Brahmic\ApiSutra\Result\ResultHandle;
use Brahmic\ApiSutra\Response\ClientResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PlainRequest;
use Brahmic\ApiSutra\Tests\Stubs\Resolver\ResolverStubClientA;
use Brahmic\ApiSutra\Tests\Stubs\Resolver\ResolverStubClientB;

describe('ClientResolver', function () {
    it('разрешает клиента по запросу', function () {
        $registry = new ClientRegistry();
        $resolver = new ClientResolver($registry);
        $client = new ResolverStubClientA(new ClientConfig(baseUrl: 'https://example.test'));

        $registry->register($client, 'Brahmic\\ApiSutra\\Tests\\Stubs\\Requests');

        $request = new PlainRequest('query');

        expect($resolver->resolve($request))->toBe($client);
    });

    it('разрешает клиента по execution-обертке', function () {
        $registry = new ClientRegistry();
        $resolver = new ClientResolver($registry);
        $client = new ResolverStubClientA(new ClientConfig(baseUrl: 'https://example.test'));

        $registry->register($client, 'Brahmic\\ApiSutra\\Tests\\Stubs\\Requests');

        $request = new PlainRequest('query');
        $execution = new RequestExecution($request, RequestOptions::empty());

        expect($resolver->resolve($execution))->toBe($client);
    });

    it('бросает исключение при чужом владельце запроса', function () {
        $registry = new ClientRegistry();
        $resolver = new ClientResolver($registry);
        $clientA = new ResolverStubClientA(new ClientConfig(baseUrl: 'https://example.test'));
        $clientB = new ResolverStubClientB(new ClientConfig(baseUrl: 'https://example.test'));

        $registry->register($clientA, 'Brahmic\\ApiSutra\\Tests\\Stubs\\Requests');

        $request = new PlainRequest('query');

        $resolver->assertOwnership($clientB, $request);
    })->throws(ConfigurationException::class);
});
