<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Request\RequestExecution;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;

beforeEach(function () {
    ContainerProviderRegistry::set(new NullContainerProvider());
});

describe('RequestExecution', function () {
    it('выбрасывает исключение без клиента', function () {
        $execution = new RequestExecution(
            request: new SimpleGetRequest('q'),
            options: RequestOptions::empty(),
        );

        expect(fn () => $execution->send())
            ->toThrow(ConfigurationException::class);
    });

    it('выбрасывает исключение без клиента при sendAsync', function () {
        $execution = new RequestExecution(
            request: new SimpleGetRequest('q'),
            options: RequestOptions::empty(),
        );

        expect(fn () => $execution->sendAsync())
            ->toThrow(ConfigurationException::class);
    });
});
