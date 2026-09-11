<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\AttributeRegistry;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Extensions\ExtensionRegistry;
use Brahmic\ApiSutra\Hooks\HookRegistry;
use Brahmic\ApiSutra\Pipeline\Hydration\ResponseHydrator;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ConfigPaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PaginatedItemsRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\UnwrapResponseRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('ResponseHydrator', function () {
    it('извлекает элементы по itemsPath', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $hydrator = new ResponseHydrator(
            $config,
            new Hydrator(new CastRegistry()),
            new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry()),
        );

        $request = new PaginatedItemsRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $data = ['data' => ['items' => [['id' => 1], ['id' => 2]]]];
        $result = $hydrator->hydrateResponse($request, $context, $data);

        expect($result)->toBe([['id' => 1], ['id' => 2]]);
    });

    it('unwrap и гидрирует DTO', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $hydrator = new ResponseHydrator(
            $config,
            new Hydrator(new CastRegistry()),
            new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry()),
        );

        $request = new UnwrapResponseRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $data = ['data' => ['item' => ['id' => 7, 'name' => 'User']]];
        $result = $hydrator->hydrateResponse($request, $context, $data);

        expect($result)->toBeInstanceOf(SimpleResponseDto::class);
        expect($result->id)->toBe(7);
        expect($result->name)->toBe('User');
    });

    it('использует itemsPath из paginationConfig', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            paginationConfig: new PaginationConfig(itemsPath: 'response.items'),
            environment: Environment::Testing,
        );
        $hydrator = new ResponseHydrator(
            $config,
            new Hydrator(new CastRegistry()),
            new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry()),
        );

        $request = new ConfigPaginatedRequest();
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $data = ['response' => ['items' => [['id' => 3], ['id' => 4]]]];
        $result = $hydrator->hydrateResponse($request, $context, $data);

        expect($result)->toBe([['id' => 3], ['id' => 4]]);
    });
});
