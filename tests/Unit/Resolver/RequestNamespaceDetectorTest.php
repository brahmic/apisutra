<?php

declare(strict_types=1);

use Acme\Blank\EmptyDiscoveryClient;
use Acme\Discovery\DiscoveryClient;
use Acme\Fallback\FallbackClient;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Resolver\ClassMapProvider;
use Brahmic\ApiSutra\Resolver\RequestNamespaceDetector;
use Brahmic\ApiSutra\Resolver\RequestScanner;

describe('RequestNamespaceDetector', function () {
    it('находит namespace запросов клиента', function () {
        $client = new DiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));

        $namespaces = $detector->detect($client);

        expect($namespaces)->toContain('Acme\\Discovery\\Requests');
    });

    it('возвращает несколько namespace при наличии разных запросов', function () {
        $client = new DiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));

        $namespaces = $detector->detect($client);

        expect($namespaces)->toContain('Acme\\Discovery\\Requests');
        expect($namespaces)->toContain('Acme\\Discovery\\Resources');
    });

    it('использует fallback-конвенции при пустом root-scan', function () {
        $client = new FallbackClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));

        $namespaces = $detector->detect($client);

        expect($namespaces)->toContain('Acme\\Fallback\\Requests');
    });

    it('бросает исключение, если запросы не найдены', function () {
        $client = new EmptyDiscoveryClient(
            new ClientConfig(baseUrl: 'https://example.test', environment: Environment::Local),
        );
        $detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));

        $detector->detect($client);
    })->throws(ConfigurationException::class);
});
