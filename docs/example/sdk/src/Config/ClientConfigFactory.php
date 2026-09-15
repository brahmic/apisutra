<?php

declare(strict_types=1);

namespace Example\Records\Config;

use Brahmic\ApiSutra\Config\ClientConfig;

final class ClientConfigFactory
{
    public static function create(string $baseUrl = 'https://records.example.test'): ClientConfig
    {
        return new ClientConfig(
            baseUrl: $baseUrl,
            hydrationRules: HydrationRulesFactory::create(),
        );
    }
}
