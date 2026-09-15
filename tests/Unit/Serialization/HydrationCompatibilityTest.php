<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Casts\DateTimeCast;
use Brahmic\ApiSutra\Casts\EnumCast;
use Brahmic\ApiSutra\Casts\JsonCast;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\DateTimeHydrationPolicy;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Dto\ConstructorDefaultDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\FallbackDefaultDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\HydrationArrayJsonDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\HydrationConstructorDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\HydrationInvalidMapDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\HydrationJsonPayloadDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\HydrationNestedJsonDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\HydrationRequiredNullableDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\InheritedNullablePayloadDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\ScalarAutoCastDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\StrictFormatNullableDateTimeDto;
use Brahmic\ApiSutra\Tests\Stubs\Enums\NonBackedStatus;
use Brahmic\ApiSutra\Tests\Stubs\Enums\TestStatus;
use Brahmic\ApiSutra\Tests\Stubs\Requests\HydrationProbeRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\HydrationConstructionProbe;
use Brahmic\ApiSutra\Tests\Support\StrictCache;
use Brahmic\ApiSutra\Transport\MockTransport;

it('сохраняет defaults fallback и выбранный null', function (): void {
    expect(HydrationRequiredNullableDto::from(['note' => null])->note)->toBeNull()
        ->and(ConstructorDefaultDto::from([])->middleName)->toBe('somename')
        ->and(ConstructorDefaultDto::from(['middle_name' => null])->middleName)->toBeNull()
        ->and(InheritedNullablePayloadDto::from(['status' => 'active'])->result)->toBeNull()
        ->and(FallbackDefaultDto::from(['primary' => null, 'secondary' => 'alternative'])->name)->toBeNull()
        ->and(FallbackDefaultDto::from([])->status)->toBe('unknown')
        ->and(StrictFormatNullableDateTimeDto::from(['created_at' => 'invalid'])->createdAt)->toBeNull();
});

it('сохраняет разрешённые scalar conversions reflection', function (string $field, mixed $input, mixed $expected): void {
    expect(ScalarAutoCastDto::from([$field => $input])->{$field})->toBe($expected);
})->with([
    ['intValue', 42.0, 42], ['intValue', '4.2e1', 42], ['intValue', '+42', 42], ['intValue', true, 1],
    ['boolValue', 'non-empty', true], ['boolValue', 2, true], ['stringValue', true, '1'], ['floatValue', true, 1.0],
]);

it('проверяет параметр конструктора без пробного вызова и сохраняет преобразование свойства', function (): void {
    HydrationConstructionProbe::$calls = 0;
    try {
        expect(HydrationConstructorDto::from(['id' => ['id' => 7]])->id)->toBe(7)
            ->and(HydrationConstructionProbe::$calls)->toBe(1)
            ->and(fn () => HydrationConstructorDto::from([]))->toThrow(HydrationException::class)
            ->and(HydrationConstructionProbe::$calls)->toBe(1);
    } finally {
        HydrationConstructionProbe::$calls = 0;
    }
});

it('сохраняет корректный JSON и нестроковые значения JsonCast', function (mixed $input, mixed $expected): void {
    expect((new JsonCast())->hydrate($input))->toBe($expected)
        ->and(HydrationJsonPayloadDto::from(['payload' => $input])->payload)->toBe($expected);
})->with([
    ['null', null], [null, null], ['false', false], ['0', 0], ['"text"', 'text'], ['[]', []], ['{}', []],
    ['{"id":9223372036854775808999}', ['id' => '9223372036854775808999']], [['ready' => true], ['ready' => true]],
]);

it('отличает неверный JSON от неподходящего типа его валидного результата', function (): void {
    try {
        HydrationArrayJsonDto::from(['payload' => 'false']);
        test()->fail('Неверная форма принята');
    } catch (HydrationException $exception) {
        expect($exception->reason)->toBe('invalid_field_type')->and($exception->path)->toBe('payload')
            ->and($exception->expected)->toBe('array')->and($exception->actual)->toBe('bool');
    }
});

it('сохраняет индекс each/itemCast и original JsonException в цепочке', function (): void {
    try {
        HydrationNestedJsonDto::from(['items' => [['value' => '{"ok":true}'], ['value' => '{broken']]]);
        test()->fail('Неверный JSON принят');
    } catch (HydrationException $exception) {
        expect($exception->path)->toBe('items[1]')->and($exception->reason)->toBe('invalid_json');
        $cause = $exception;
        while ($cause->getPrevious() !== null) {
            $cause = $cause->getPrevious();
        }
        expect($cause)->toBeInstanceOf(JsonException::class);
    }
});

it('сохраняет конфигурационную ошибку неверной timezone и map', function (): void {
    $cast = DateTimeCast::fromHydrationPolicy(new DateTimeHydrationPolicy(defaultTimezone: 'Fixture/Missing'));
    expect(fn () => $cast->hydrate('2024-01-01'))->toThrow(ConfigurationException::class)
        ->and(fn () => HydrationInvalidMapDto::from(['items' => [['kind' => 'known']]]))->toThrow(ConfigurationException::class);
});

it('доставляет ошибки JsonCast через promise и throwOnErrors с данными из кеша', function (bool $async): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['payload' => '{"ok":true}'])]);
    $cache = new StrictCache();
    $config = new ClientConfig(baseUrl: 'https://fixture.test', environment: Environment::Testing, cacheStore: $cache);
    $request = (new HydrationProbeRequest(HydrationJsonPayloadDto::class))->setClient(new TestClient($config, $transport));
    expect($request->send()->dataOrFail()->payload)->toBe(['ok' => true]);
    // Моделируем сохранённый HTTP-ответ, который не проходит текущий контракт DTO.
    $replaced = false;
    foreach ($cache->keys as $key) {
        $entry = $cache->get($key);
        if (is_array($entry) && isset($entry['body'])) {
            $entry['body'] = '{"payload":"{broken"}';
            $cache->set($key, $entry);
            $replaced = true;
            break;
        }
    }
    expect($replaced)->toBeTrue();
    for ($index = 0; $index < 2; $index++) {
        $handle = $async ? $request->sendAsync() : $request->send();
        expect($handle->raw()->errors->first()->code->value)->toBe('hydration_error')
            ->and($handle->resolved()->error()->sdkCode->value)->toBe('hydration_error')
            ->and(fn () => $handle->dataOrFail())->toThrow(HydrationException::class);
    }
    $throwing = (new HydrationProbeRequest(HydrationJsonPayloadDto::class))->setClient(new TestClient($config->with(throwOnErrors: true), $transport));
    expect(static function () use ($throwing, $async): void {
        ($async ? $throwing->sendAsync() : $throwing->send())->raw();
    })->toThrow(HydrationException::class);
    expect($transport->getRecorded())->toHaveCount(1);
})->with([false, true]);


it('отличает неверный тип enum от неизвестного значения и неверного объявления', function (): void {
    $cast = new EnumCast(TestStatus::class);
    expect($cast->hydrate('active'))->toBe(TestStatus::Active)
        ->and($cast->hydrate('unknown'))->toBeNull()
        ->and(fn () => (new EnumCast(NonBackedStatus::class))->hydrate('unknown'))->toThrow(ConfigurationException::class);
    try {
        $cast->hydrate(['fixture-secret']);
        test()->fail('Неверный тип enum принят');
    } catch (HydrationException $exception) {
        expect($exception->reason)->toBe('invalid_field_type')
            ->and($exception->actual)->toBe('array')
            ->and($exception->getMessage())->not->toContain('fixture-secret');
    }
});

it('безопасно классифицирует ошибку явного DateTimeCast', function (mixed $input): void {
    try {
        (new DateTimeCast())->hydrate($input);
        test()->fail('Неверная дата принята');
    } catch (HydrationException $exception) {
        expect($exception->reason)->toBe('invalid_datetime')
            ->and($exception->expected)->toBe('date: ' . DATE_ATOM)
            ->and($exception->getMessage())->not->toContain('fixture-secret');
    }
})->with([['fixture-secret'], ["fixture-secret\0"], [['fixture-secret']]]);
