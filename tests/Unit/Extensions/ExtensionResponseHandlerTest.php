<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Extensions\TestResponseExtension;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Support\TestClientFactory;

describe('Extension response handler', function () {
    it('перехватывает ответ и вызывается один раз при boot', function () {
        TestResponseExtension::reset();

        $client = TestClientFactory::make(
            [
                SimpleGetRequest::class => MockResponse::success(['value' => 1]),
            ],
            [
                'extensions' => [new TestResponseExtension()],
            ],
        );

        $request = new SimpleGetRequest('payload');
        $request->setClient($client);

        $first = $request->send()->raw();
        $second = $request->send()->raw();

        expect($first->data)->toBe(['handled' => true, 'status' => 200]);
        expect($second->data)->toBe(['handled' => true, 'status' => 200]);
        expect(TestResponseExtension::$bootCount)->toBe(1);
    });
});
