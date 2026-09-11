<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Laravel\RequestFactory;
use Brahmic\ApiSutra\Laravel\RequestFactory\PayloadKeys;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SerializationRequest;

describe('RequestFactory', function () {
    it('создаёт запрос из структурированного массива', function () {
        $factory = new RequestFactory();
        $source = [
            PayloadKeys::ROUTE => ['id' => '123'],
            PayloadKeys::QUERY => ['filters' => ['a', 'b']],
            PayloadKeys::BODY => [
                'payload' => ['data' => 'payload'],
                'plainValue' => 'plain',
            ],
            PayloadKeys::HEADERS => ['X-Custom' => ['header-value']],
            PayloadKeys::FILES => [],
        ];

        $request = $factory->make(SerializationRequest::class, $source);

        expect($request)->toBeInstanceOf(SerializationRequest::class);
        expect($request->id)->toBe('123');
        expect($request->filters)->toBe(['a', 'b']);
        expect($request->payload)->toBe('payload');
        expect($request->header)->toBe('header-value');
        expect($request->plainValue)->toBe('plain');
    });
});
