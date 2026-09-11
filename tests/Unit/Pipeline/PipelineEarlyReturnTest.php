<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Hooks\Hook;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Tests\Stubs\HookedClient;
use Brahmic\ApiSutra\Tests\Stubs\Hooks\EarlyReturnHook;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PlainRequest;
use Brahmic\ApiSutra\Transport\MockTransport;

describe('Pipeline early return', function () {
    it('останавливает pipeline и не вызывает транспорт', function () {
        $hooks = new Brahmic\ApiSutra\Hooks\HookRegistry();
        $hooks->on(Hook::BeforeSend, new EarlyReturnHook());

        $transport = new MockTransport();
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $client = new HookedClient($config, $transport, $hooks);

        $request = new PlainRequest('q');
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->status)->toBe(ResultStatus::SUCCESS);
        expect($result->data)->toBe(['ok' => true]);
        expect($transport->getRecorded())->toHaveCount(0);
    });
});
