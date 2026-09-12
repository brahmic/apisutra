<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Collections\RawCollection;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PolymorphicOwnerOrganizationDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PolymorphicOwnerPersonDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PolymorphicOwnersArrayKeyErrorDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PolymorphicOwnersArrayKeyKeepRawDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PolymorphicOwnersArrayKeySkipDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PolymorphicOwnersArrayValueKeepRawDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PolymorphicOwnersRawCollectionKeyKeepRawDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PolymorphicOwnersRawCollectionValueKeepRawDto;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SerializationRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('Nested discriminator mode', function () {
    it('гидрирует key-mode в array и сохраняет неизвестный вариант как raw', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plain'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = $hydrator->hydrate([
            'owners' => [
                ['person' => ['name' => 'Виктор']],
                ['organization' => ['title' => 'ООО Ромашка']],
                ['foreign' => ['id' => 'x-1']],
            ],
        ], PolymorphicOwnersArrayKeyKeepRawDto::class, $context);

        expect($dto->owners)->toHaveCount(3)
            ->and($dto->owners[0])->toBeInstanceOf(PolymorphicOwnerPersonDto::class)
            ->and($dto->owners[0]->name)->toBe('Виктор')
            ->and($dto->owners[1])->toBeInstanceOf(PolymorphicOwnerOrganizationDto::class)
            ->and($dto->owners[1]->title)->toBe('ООО Ромашка')
            ->and($dto->owners[2])->toBe(['foreign' => ['id' => 'x-1']]);
    });

    it('гидрирует key-mode в RawCollection и сохраняет неизвестный вариант как raw', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plain'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = $hydrator->hydrate([
            'owners' => [
                ['person' => ['name' => 'Виктор']],
                ['organization' => ['title' => 'ООО Ромашка']],
                ['foreign' => ['id' => 'x-1']],
            ],
        ], PolymorphicOwnersRawCollectionKeyKeepRawDto::class, $context);

        $owners = $dto->owners->all();

        expect($dto->owners)->toBeInstanceOf(RawCollection::class)
            ->and($owners)->toHaveCount(3)
            ->and($owners[0])->toBeInstanceOf(PolymorphicOwnerPersonDto::class)
            ->and($owners[1])->toBeInstanceOf(PolymorphicOwnerOrganizationDto::class)
            ->and($owners[2])->toBe(['foreign' => ['id' => 'x-1']]);
    });

    it('поддерживает unknownVariant Skip для key-mode', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plain'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = $hydrator->hydrate([
            'owners' => [
                ['person' => ['name' => 'Виктор']],
                ['foreign' => ['id' => 'x-1']],
                ['organization' => ['title' => 'ООО Ромашка']],
            ],
        ], PolymorphicOwnersArrayKeySkipDto::class, $context);

        expect($dto->owners)->toHaveCount(2)
            ->and($dto->owners[0])->toBeInstanceOf(PolymorphicOwnerPersonDto::class)
            ->and($dto->owners[1])->toBeInstanceOf(PolymorphicOwnerOrganizationDto::class);
    });

    it('поддерживает unknownVariant Error для key-mode', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plain'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $hydrator->hydrate([
            'owners' => [
                ['person' => ['name' => 'Виктор']],
                ['foreign' => ['id' => 'x-1']],
            ],
        ], PolymorphicOwnersArrayKeyErrorDto::class, $context))
            ->toThrow(HydrationException::class, 'Некорректные данные в owners[1]');
    });

    it('сохраняет value-mode для array', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plain'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = $hydrator->hydrate([
            'owners' => [
                ['kind' => 'person', 'name' => 'Виктор'],
                ['kind' => 'organization', 'title' => 'ООО Ромашка'],
                ['kind' => 'foreign', 'payload' => ['id' => 'x-1']],
            ],
        ], PolymorphicOwnersArrayValueKeepRawDto::class, $context);

        expect($dto->owners)->toHaveCount(3)
            ->and($dto->owners[0])->toBeInstanceOf(PolymorphicOwnerPersonDto::class)
            ->and($dto->owners[1])->toBeInstanceOf(PolymorphicOwnerOrganizationDto::class)
            ->and($dto->owners[2])->toBe(['kind' => 'foreign', 'payload' => ['id' => 'x-1']]);
    });

    it('сохраняет value-mode для RawCollection', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plain'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = $hydrator->hydrate([
            'owners' => [
                ['kind' => 'person', 'name' => 'Виктор'],
                ['kind' => 'organization', 'title' => 'ООО Ромашка'],
                ['kind' => 'foreign', 'payload' => ['id' => 'x-1']],
            ],
        ], PolymorphicOwnersRawCollectionValueKeepRawDto::class, $context);
        $owners = $dto->owners->all();

        expect($dto->owners)->toBeInstanceOf(RawCollection::class)
            ->and($owners)->toHaveCount(3)
            ->and($owners[0])->toBeInstanceOf(PolymorphicOwnerPersonDto::class)
            ->and($owners[1])->toBeInstanceOf(PolymorphicOwnerOrganizationDto::class)
            ->and($owners[2])->toBe(['kind' => 'foreign', 'payload' => ['id' => 'x-1']]);
    });
});
