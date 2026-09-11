<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\DependencyTokenRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\DependsOnMainRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\DependsOnParallelRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

describe('Depends-on flow', function () {
    it('прокидывает данные из зависимостей в основной запрос', function () {
        $transport = new MockTransport();
        $transport->fake([
            DependencyTokenRequest::class => MockResponse::success(['token' => 't-1']),
            DependsOnMainRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://provider.test',
                throwOnErrors: true,
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new DependsOnMainRequest();
        $request->setClient($client);

        $request->send();

        $recorded = $transport->getRecorded();
        $mainRequest = null;
        foreach ($recorded as $item) {
            if (($item->meta['requestClass'] ?? null) === DependsOnMainRequest::class) {
                $mainRequest = $item;
                break;
            }
        }

        expect($mainRequest)->not->toBeNull();
        $query = $mainRequest->meta['query'] ?? [];
        expect($query['token']['value'] ?? null)->toBe('t-1');
    });

    it('запрещает parallel mode для depends-on', function () {
        $transport = new MockTransport();
        $transport->fake([
            DependencyTokenRequest::class => MockResponse::success(['token' => 't-1']),
            DependsOnMainRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://provider.test',
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new DependsOnParallelRequest();
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->isFailed())->toBeTrue();
        expect($result->errors->first()?->code)->toBe(ErrorCode::ConfigurationError);
        expect($result->exception)->toBeInstanceOf(ConfigurationException::class);
        expect($result->errors->first()?->message)->toBe('DependsOnExecutor поддерживает только Sequential');
    });

    it('пробрасывает исключение при throwOnErrors', function () {
        $transport = new MockTransport();
        $transport->fake([
            DependencyTokenRequest::class => MockResponse::success(['token' => 't-1']),
            DependsOnMainRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new TestClient(
            new ClientConfig(
                baseUrl: 'https://provider.test',
                throwOnErrors: true,
                environment: Environment::Testing,
            ),
            $transport,
        );

        $request = new DependsOnParallelRequest();
        $request->setClient($client);

        expect(fn () => $request->send())
            ->toThrow(ConfigurationException::class, 'DependsOnExecutor поддерживает только Sequential');
    });
});
