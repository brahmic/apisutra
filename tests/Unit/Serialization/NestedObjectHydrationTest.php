<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\AddressDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\AmbiguousCardinalityDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\AmbiguousObjectDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\ArrayFieldObjectDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\ContractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\CustomCollectionsDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\InterfaceObjectDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\InvalidObjectTypeDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\InvalidObjectOptionsDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\MappedObjectDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\NestedCastPriorityDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\ObjectDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\PropertyObjectDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\UnionObjectDto;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\HydrationConstructionProbe;
use Brahmic\ApiSutra\Transport\MockTransport;

it('гидрирует одиночный Nested через конструктор и public-свойство', function (string $type, bool $object): void {
    HydrationConstructionProbe::$calls = 0;
    $value = $object ? (object) ['city' => 'Sample'] : ['city' => 'Sample'];

    $dto = Hydrator::default()->hydrate(['address' => $value], $type);

    expect($dto->address)->toBeInstanceOf(AddressDto::class)
        ->and($dto->address->city)->toBe('Sample')
        ->and(HydrationConstructionProbe::$calls)->toBe(1);
})->with([ObjectDto::class, PropertyObjectDto::class, UnionObjectDto::class])->with([false, true]);

it('использует тип свойства и Nested.from с fallback для одиночного DTO', function (
    array $payload,
    ?string $city,
): void {
    $dto = Hydrator::default()->hydrate($payload, MappedObjectDto::class);
    expect($dto->address?->city)->toBe($city);
})->with([
    [['profile' => ['address' => ['city' => 'primary']], 'legacy' => ['address' => ['city' => 'fallback']]], 'primary'],
    [['legacy' => ['address' => ['city' => 'fallback']]], 'fallback'],
    [['profile' => ['address' => null], 'legacy' => ['address' => ['city' => 'fallback']]], null],
    [[], null],
]);

it('принимает готовый PHP DTO и вызывает конструктор гидратации один раз', function (): void {
    $address = new AddressDto('Sample');
    HydrationConstructionProbe::$calls = 0;
    $dto = Hydrator::default()->hydrate(['address' => $address], ObjectDto::class);
    expect($dto->address->city)->toBe('Sample')->and(HydrationConstructionProbe::$calls)->toBe(1);
});

it('сохраняет From fallback и приоритет Nested над Cast свойства', function (): void {
    $dto = Hydrator::default()->hydrate(['alternative' => ['city' => 'Sample']], NestedCastPriorityDto::class);
    expect($dto->address->city)->toBe('Sample');
});

it('гидрирует единственное поле-массив внутри одиночного объекта', function (): void {
    $dto = Hydrator::default()->hydrate(['source' => ['values' => ['nested' => []]]], ArrayFieldObjectDto::class);
    expect($dto->child->values)->toBe(['nested' => []]);
});

it('использует явный конкретный класс для интерфейсного свойства', function (): void {
    $dto = Hydrator::default()->hydrate(['item' => ['id' => 7, 'name' => 'item']], InterfaceObjectDto::class);
    expect($dto->item->id)->toBe(7)->and($dto->item->name)->toBe('item');
});

it('возвращает ошибку одиночного объекта без фиктивного индекса и TypeError', function (
    array $payload,
    string $reason,
    string $path,
): void {
    HydrationConstructionProbe::$calls = 0;
    try {
        Hydrator::default()->hydrate($payload, ObjectDto::class);
        test()->fail('Некорректный объект принят');
    } catch (HydrationException $error) {
        expect($error->reason)->toBe($reason)->and($error->path)->toBe($path)
            ->and(HydrationConstructionProbe::$calls)->toBe(0);
    }
})->with([
    [[], 'required_field_missing', 'address'],
    [['address' => null], 'null_not_allowed', 'address'],
    [['address' => false], 'unexpected_response_shape', 'address'],
    [['address' => 'secret-fixture'], 'unexpected_response_shape', 'address'],
    [['address' => [['city' => 'Sample']]], 'unexpected_response_shape', 'address'],
    [['address' => []], 'required_field_missing', 'address.city'],
    [['address' => ['wrapped' => ['city' => 'Sample']]], 'required_field_missing', 'address.city'],
    [['address' => ['city' => []]], 'invalid_field_type', 'address.city'],
]);

it('отклоняет неоднозначную или несовместимую декларацию Nested', function (string $type): void {
    expect(fn (): object => Hydrator::default()->hydrate(['address' => ['city' => 'Sample', 'values' => []]], $type))
        ->toThrow(ConfigurationException::class);
})->with([
    AmbiguousObjectDto::class,
    AmbiguousCardinalityDto::class,
    InvalidObjectTypeDto::class,
    InvalidObjectOptionsDto::class,
]);

it('сохраняет пользовательские коллекции с конструктором, фабрикой и Traversable', function (): void {
    $items = [['city' => 'First'], ['city' => 'Second']];
    $dto = Hydrator::default()->hydrate(
        ['constructor' => $items, 'factory' => $items, 'iterator' => $items],
        CustomCollectionsDto::class,
    );
    expect($dto->constructor->items[0]->city)->toBe('First')
        ->and($dto->factory->items[1]->city)->toBe('Second')
        ->and($dto->iterator->toArray()[0])->toBeInstanceOf(AddressDto::class);
});

it('доставляет одиночный Nested через JSON и Returns без дополнительных попыток', function (
    bool $async,
    bool $invalid,
): void {
    $payload = ['data' => ['address' => $invalid ? ['wrapped' => ['city' => 'Sample']] : ['city' => 'Sample']]];
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make(json_encode($payload, JSON_THROW_ON_ERROR))]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test',
        containerProvider: new NullContainerProvider(),
    ), $transport);
    $request = new ContractRequest(ObjectDto::class, 'data');
    $handle = $async ? $client->sendAsync($request) : $client->send($request);
    $result = $handle->raw();

    expect($transport->getRecorded())->toHaveCount(1)->and($result->response->status)->toBe(200);
    if ($invalid) {
        expect($result->exception)->toBeInstanceOf(HydrationException::class)
            ->and($result->exception->reason)->toBe('required_field_missing')
            ->and($result->exception->path)->toBe('data.address.city')
            ->and($result->errors->first()->code->value)->toBe('hydration_error')
            ->and(fn () => $handle->dataOrFail())->toThrow(HydrationException::class);
    } else {
        expect($result->isSuccess())->toBeTrue()->and($result->data->address->city)->toBe('Sample');
    }
})->with([false, true])->with([false, true]);
