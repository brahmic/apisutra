<?php

declare(strict_types=1);

use Acme\Discovery\FlowClient;
use Acme\Discovery\Requests\DiscoveryRequest;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Resolver\ClassMapProvider;
use Brahmic\ApiSutra\Resolver\ClientDiscoveryCache;
use Brahmic\ApiSutra\Resolver\ClientDiscoveryService;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Brahmic\ApiSutra\Resolver\ClientResolver;
use Brahmic\ApiSutra\Resolver\DiscoveryOptions;
use Brahmic\ApiSutra\Resolver\RequestNamespaceDetector;
use Brahmic\ApiSutra\Resolver\RequestScanner;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Discovery full flow', function () {
    it('проходит discovery → pipeline → execution → result', function () {
        $transport = new MockTransport();
        $transport->fake([
            DiscoveryRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new FlowClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $registry = new ClientRegistry();
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
        $cache = new ClientDiscoveryCache();
        $service = new ClientDiscoveryService($registry, $detector, $cache);

        $service->registerAuto($client, DiscoveryOptions::forceOff());

        $resolver = new ClientResolver($registry);
        $request = new DiscoveryRequest();
        $request->setClient($resolver->resolve($request));

        $result = $request->send()->raw();

        expect($result->isSuccess())->toBeTrue();
        expect($result->data)->toBe(['ok' => true]);
    });
});
