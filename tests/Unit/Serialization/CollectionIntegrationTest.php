<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Serialization\DtoSerializer;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Tests\Stubs\Collections\OutputItemCollection;
use Brahmic\ApiSutra\Tests\Stubs\Dto\CollectionDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\CollectionEachDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\CollectionMismatchDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\NestedDataUriFilesDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\NestedEachDataUriFilesDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\OutputItemDto;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SerializationRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('Collections integration', function () {
    it('гидрирует в typed-коллекцию через Nested', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = $hydrator->hydrate([
            'items' => [
                ['id' => 1, 'label' => 'A'],
                ['id' => 2, 'label' => 'B'],
            ],
        ], CollectionDto::class, $context);

        expect($dto->items)->toBeInstanceOf(OutputItemCollection::class)
            ->and($dto->items->count())->toBe(2)
            ->and($dto->items->first()?->id)->toBe(1);
    });

    it('бросает исключение при несовпадении типа коллекции', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $hydrator->hydrate([
            'items' => [
                ['value' => 'x'],
            ],
        ], CollectionMismatchDto::class, $context))
            ->toThrow(ConfigurationException::class);
    });

    it('сериализует коллекцию через toArray', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new CollectionDto(new OutputItemCollection([
            new OutputItemDto(1, 'A'),
        ]));

        $data = $serializer->serialize($dto, $context);

        expect($data['items'] ?? null)->toBe([
            ['id' => 1, 'label' => 'A'],
        ]);
    });

    it('гидрирует коллекцию с Nested(each)', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = $hydrator->hydrate([
            'items' => [
                ['value' => ['id' => 1, 'label' => 'A']],
                ['value' => ['id' => 2, 'label' => 'B']],
            ],
        ], CollectionEachDto::class, $context);

        expect($dto->items)->toBeInstanceOf(OutputItemCollection::class)
            ->and($dto->items->count())->toBe(2)
            ->and($dto->items->first()?->id)->toBe(1);
    });

    it('гидрирует list<data-uri-string> через Nested(itemCast)', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $dto = $hydrator->hydrate([
            'faces' => [
                'data:image/jpeg;base64, ' . base64_encode('first'),
                'data:image/jpeg;base64,' . base64_encode('second'),
            ],
        ], NestedDataUriFilesDto::class);

        expect($dto->faces->count())->toBe(2)
            ->and($dto->faces->first()?->content())->toBe('first')
            ->and($dto->faces->get(1)?->content())->toBe('second');
    });

    it('применяет itemCast после each в Nested(each)', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $dto = $hydrator->hydrate([
            'faces' => [
                ['value' => 'data:image/jpeg;base64, ' . base64_encode('one')],
                ['value' => 'data:image/jpeg;base64, ' . base64_encode('two')],
            ],
        ], NestedEachDataUriFilesDto::class);

        expect($dto->faces->count())->toBe(2)
            ->and($dto->faces->first()?->content())->toBe('one')
            ->and($dto->faces->get(1)?->content())->toBe('two');
    });
});
