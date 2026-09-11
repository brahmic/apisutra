<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Hooks\Hook;
use Brahmic\ApiSutra\Tests\Stubs\HookedClient;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\ThrowingHook;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PlainRequest;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\Hooks\HookRegistry;

describe('Pipeline hook exceptions', function () {
    it('оборачивает исключения хука в ExecutionResult', function (Hook $hook, int $expectedCalls, string $expectedExceptionClass) {
        $hooks = new HookRegistry();
        $hooks->on($hook, new ThrowingHook('boom'));

        $transport = new MockTransport();
        $transport->fake([
            PlainRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new HookedClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
            $hooks,
        );

        $request = new PlainRequest('q');
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->isFailed())->toBeTrue();
        expect($result->errors->first()?->message)->toBe('boom');
        expect($result->exception)->toBeInstanceOf($expectedExceptionClass);
        expect($transport->getRecorded())->toHaveCount($expectedCalls);
    })->with([
        'BeforeSend' => [Hook::BeforeSend, 0, RuntimeException::class],
        'AfterResponse' => [Hook::AfterResponse, 1, RuntimeException::class],
        'BeforeHydrate' => [Hook::BeforeHydrate, 1, RuntimeException::class],
        'AfterHydrate' => [Hook::AfterHydrate, 1, RuntimeException::class],
    ]);

    it('пробрасывает исключение хука при throwOnErrors', function () {
        $hooks = new HookRegistry();
        $hooks->on(Hook::BeforeSend, new ThrowingHook('boom'));

        $transport = new MockTransport();
        $transport->fake([
            PlainRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new HookedClient(
            new ClientConfig(
                baseUrl: 'https://api.test',
                throwOnErrors: true,
                environment: Environment::Testing,
            ),
            $transport,
            $hooks,
        );

        $request = new PlainRequest('q');
        $request->setClient($client);

        expect(fn () => $request->send())
            ->toThrow(RuntimeException::class, 'boom');
    });
});
