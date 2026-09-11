<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Hooks\Hook;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Hooks\HookRegistry;
use Brahmic\ApiSutra\Tests\Stubs\HookedClient;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\EarlyReturnHook;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PlainRequest;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Pipeline EarlyReturn stages', function () {
    it('останавливает пайплайн на разных стадиях', function (Hook $hook, int $expectedCalls, ResultStatus $expectedStatus) {
        $hooks = new HookRegistry();
        $hooks->on($hook, new EarlyReturnHook());

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

        expect($result->status)->toBe($expectedStatus);
        if ($expectedStatus === ResultStatus::SUCCESS) {
            expect($result->data)->toBe(['ok' => true]);
        }
        expect($transport->getRecorded())->toHaveCount($expectedCalls);
    })->with([
        'BeforeSend' => [Hook::BeforeSend, 0, ResultStatus::SUCCESS],
        'AfterResponse' => [Hook::AfterResponse, 1, ResultStatus::FAILED],
        'BeforeHydrate' => [Hook::BeforeHydrate, 1, ResultStatus::SUCCESS],
        'AfterHydrate' => [Hook::AfterHydrate, 1, ResultStatus::SUCCESS],
    ]);
});
