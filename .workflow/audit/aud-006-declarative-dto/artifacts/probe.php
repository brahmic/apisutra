<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\AuditClient;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\AuditRequest;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\CombinedListDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ConstructorCounter;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\CountedDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\DiscriminatedDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ExplicitCastDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\GuardedTypedDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ListDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\MappedDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\MixedGraphDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\NullCastDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\NullProviderDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\PathDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\PathListDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\PlainGraphDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\PlainScalarDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\RequiredNullableDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\RequiredTypedDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\StrictGraphDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\StrictListDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\StrictScalarCast;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\StrictScalarDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ThrowOnNullCast;
use Brahmic\ApiSutra\Transport\MockTransport;

$root = dirname(__DIR__, 4);
$loader = require $root . '/vendor/autoload.php';
// Фикстуры аудита автономны и не изменяют autoload-dev поставляемого пакета.
$loader->addPsr4('Brahmic\\ApiSutra\\Tests\\Stubs\\DtoFeedbackAudit\\', __DIR__ . '/Stubs');

// Любая скрытая попытка воспользоваться Illuminate делает проверку неуспешной.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Illuminate\\')) {
        throw new RuntimeException('Проверка не должна загружать Illuminate: ' . $class);
    }
}, prepend: true);

$hydrator = new Hydrator(new CastRegistry());
$observations = [];

/** Ожидания фиксируют текущую реализацию, включая пробелы относительно issue/002. */
$probe = static function (string $id, string $description, Closure $run, array $expected) use (&$observations): void {
    try {
        $actual = ['ok' => true, 'value' => $run()];
    } catch (Throwable $exception) {
        $actual = ['ok' => false, 'exception' => $exception::class];
        if ($exception instanceof HydrationException) {
            $actual += $exception->context();
        } else {
            $actual['message'] = $exception->getMessage();
        }
    }

    $matches = true;
    foreach ($expected as $key => $value) {
        if (!array_key_exists($key, $actual) || $actual[$key] !== $value) {
            $matches = false;
        }
    }
    $observations[] = compact('id', 'description', 'expected', 'actual', 'matches');
};

$accepted = static fn (mixed $value): array => ['ok' => true, 'value' => $value];
$invalid = static fn (string $path): array => ['ok' => false, 'reason' => 'invalid_field_type', 'path' => $path];

/** @return array{0: ExecutionResult, 1: int} */
$send = static function (string $type, array $payload, ?string $unwrap = null, array $casts = [], bool $async = false): array {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success($payload)]);
    $client = new AuditClient(new ClientConfig(
        baseUrl: 'https://fixture.invalid',
        environment: Environment::Testing,
        containerProvider: new NullContainerProvider(),
        casts: $casts,
        retry: new RetryConfig(attempts: 3, baseDelay: 0, jitter: false),
    ), $transport);
    $request = new AuditRequest($type, $unwrap);
    $result = ($async ? $client->sendAsync($request) : $client->send($request))->raw();

    return [$result, count($transport->getRecorded())];
};

// AS-1: типовые преобразования и существующий профильный способ отказа от них.
$scalarCases = [
    ['count', 123, 123, true], ['count', 0, 0, true],
    ['count', PHP_INT_MIN, PHP_INT_MIN, true], ['count', PHP_INT_MAX, PHP_INT_MAX, true],
    ['count', '123', 123, false], ['count', 123.0, 123, false], ['count', true, 1, false],
    ['enabled', true, true, true], ['enabled', false, false, true],
    ['enabled', 'false', false, false], ['enabled', 'true', true, false],
    ['enabled', 0, false, false], ['enabled', 1, true, false],
    ['label', '0', '0', true], ['label', '', '', true],
    ['label', 0, '0', false], ['label', 1.5, '1.5', false], ['label', false, '', false],
];
foreach ($scalarCases as $index => [$field, $input, $defaultValue, $strictAccepts]) {
    $probe('AS1-default-' . ($index + 1), 'Текущее приведение ' . $field,
        static fn (): mixed => $hydrator->hydrate([$field => $input], PlainScalarDto::class)->{$field},
        $accepted($defaultValue));
    $probe('AS1-profile-' . ($index + 1), 'Профильный scalar-cast для ' . $field,
        static fn (): mixed => StrictScalarDto::from([$field => $input])->{$field},
        $strictAccepts ? $accepted($input) : $invalid($field));
}
$probe('AS1-overflow', 'Переполнение штатного int не принимается',
    static fn (): object => $hydrator->hydrate(['count' => (string) PHP_INT_MAX . '0'], PlainScalarDto::class),
    ['ok' => false, 'reason' => 'integer_out_of_range', 'path' => 'count']);
$probe('AS1-profile-overflow', 'Строгий профиль отказывает переполненной строке до преобразования',
    static fn (): object => StrictScalarDto::from(['count' => (string) PHP_INT_MAX . '0']), $invalid('count'));
$probe('AS1-child', 'Профиль наследуется DTO вложенного объекта',
    static fn (): object => StrictGraphDto::from(['child' => ['count' => '7']]), $invalid('child.count'));
$probe('AS1-items', 'Профиль наследуется DTO элементов списка',
    static fn (): object => StrictGraphDto::from(['items' => [['count' => '7']]]), $invalid('items[0].count'));
$probe('AS1-unbound-child', 'Профиль родителя не распространяется на независимый plain-класс',
    static fn (): int => MixedGraphDto::from(['items' => [['count' => '7']]])->items[0]->count, $accepted(7));
$probe('AS1-explicit-cast', 'Явное преобразование имеет приоритет над профильным cast',
    static fn (): int => ExplicitCastDto::from(['count' => '7'])->count, $accepted(7));
$probe('AS1-after-cast', 'После identity-cast reflection всё ещё преобразует строку в int',
    static fn (): int => ExplicitCastDto::from(['unchecked' => '7'])->unchecked, $accepted(7));
$probe('AS1-after-cast-array', 'Невозможный тип после явного cast диагностируется',
    static fn (): object => ExplicitCastDto::from(['unchecked' => []]), $invalid('unchecked'));
$probe('AS1-registry', 'Переданный Hydrator registry не задаёт профиль DTO', static function (): int {
    $casts = new CastRegistry();
    $casts->register('int', new StrictScalarCast('int'));

    return (new Hydrator($casts))->hydrate(['count' => '7'], PlainScalarDto::class)->count;
}, $accepted(7));
$probe('AS1-client-casts', 'ClientConfig.casts не заменяет профиль гидратации DTO',
    static fn (): int => $send(PlainScalarDto::class, ['count' => '7'], casts: ['int' => new StrictScalarCast('int')])[0]->data->count,
    $accepted(7));
$probe('AS1-isolation', 'Профиль не меняет ранее созданный default-hydrator', static function () use ($hydrator): int {
    StrictScalarDto::from(['count' => 7]);

    return $hydrator->hydrate(['count' => '7'], PlainScalarDto::class)->count;
}, $accepted(7));
foreach ([false, true] as $async) {
    $probe('AS1-pipeline-' . (int) $async, 'Returns сохраняет reason, HTTP-ответ и одну попытку',
        static function () use ($send, $async): array {
            [$result, $calls] = $send(StrictScalarDto::class, ['data' => ['count' => '7']], 'data', async: $async);

            return [$result->errors->first()?->code->value, $result->exception?->reason,
                $result->exception?->path, $result->response?->status, $calls];
        }, $accepted(['hydration_error', 'invalid_field_type', 'data.count', 200, 1]));
}

// AS-2: неизвестные поля, ключ приёмника и сохранение исходного HTTP-ответа.
$probe('AS2-extras', 'Неописанные поля не попадают в extras',
    static fn (): array => $hydrator->hydrate(['record_id' => 7, 'active' => false, 'future' => null, 'zero' => 0,
        'empty' => '', 'list' => [], 'nested' => ['x' => false]], MappedDto::class)->extras, $accepted([]));
$probe('AS2-collision', 'Существующий ключ extras читается как обычное поле',
    static fn (): array => $hydrator->hydrate(['record_id' => 7, 'extras' => ['own' => 1], 'future' => 2], MappedDto::class)->extras,
    $accepted(['own' => 1]));
$probe('AS2-raw-response', 'Полные данные остаются в исходном ответе, но не в DTO',
    static function () use ($send): array {
        [$result] = $send(MappedDto::class, ['record_id' => 7, 'future' => null]);

        return [$result->data->extras, array_key_exists('future', $result->response->json())];
    }, $accepted([[], true]));

// AS-3: plain DTO, mappings, вложенность и происхождение пути ошибки.
$probe('AS3-plain-object', 'Plain readonly создаётся из объекта без контекста',
    static fn (): int => $hydrator->hydrate((object) ['count' => 7], PlainScalarDto::class)->count, $accepted(7));
$probe('AS3-plain-nested', 'Native-тип plain child не запускает автоматическую гидрацию массива',
    static fn (): object => $hydrator->hydrate(['child' => ['count' => 7]], PlainGraphDto::class), $invalid('child'));
$probe('AS3-plain-instance', 'Готовый plain child принимается по native-типу',
    static fn (): int => $hydrator->hydrate(['child' => new PlainScalarDto(count: 7)], PlainGraphDto::class)->child->count,
    $accepted(7));
$probe('AS3-plain-list', 'Nested умеет создавать plain readonly элементы',
    static fn (): int => $hydrator->hydrate(['items' => [['count' => 7]]], ListDto::class)->items[0]->count, $accepted(7));
$probe('AS3-discriminator', 'Известный вариант создаётся, неизвестный остаётся raw',
    static function () use ($hydrator): array {
        $dto = $hydrator->hydrate(['items' => [['kind' => 'known', 'record_id' => 7], ['kind' => 'future', 'x' => false]]], DiscriminatedDto::class);

        return [$dto->items[0]->id, $dto->items[1]];
    }, $accepted([7, ['kind' => 'future', 'x' => false]]));
$probe('AS3-known-invalid', 'Известный повреждённый вариант не становится raw',
    static fn (): object => $hydrator->hydrate(['items' => [['kind' => 'known', 'record_id' => []]]], DiscriminatedDto::class),
    $invalid('items[0].id'));
$probe('AS3-fallback', 'From использует fallback при отсутствии primary',
    static fn (): int => $hydrator->hydrate(['legacy' => ['batch_id' => 7]], PathDto::class)->batchId, $accepted(7));
$probe('AS3-source-path', 'Ошибка хранит DTO path вместо выбранного source path',
    static fn (): object => $hydrator->hydrate(['legacy' => ['batch_id' => []]], PathDto::class), $invalid('batchId'));
$probe('AS3-unwrap-path', 'В pipeline путь объединяет unwrap и DTO path, без source mapping',
    static function () use ($send): array {
        [$result, $calls] = $send(PathListDto::class, ['data' => ['records' => [
            ['profile' => ['batch_id' => 7]], ['legacy' => ['batch_id' => []]],
        ], 'token' => 'fixture-only-secret']], 'data');
        $context = $result->errors->first()->context;

        return [$context['path'], isset($context['sourcePath']),
            str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'fixture-only-secret'), $calls];
    }, $accepted(['data.items[1].batchId', false, false, 1]));
$probe('AS3-constructor', 'Валидные данные вызывают конструктор ровно один раз', static function () use ($hydrator): int {
    ConstructorCounter::$calls = 0;
    $hydrator->hydrate(['id' => 7], CountedDto::class);

    return ConstructorCounter::$calls;
}, $accepted(1));
$probe('AS3-constructor-invalid', 'Неподходящий native-тип не вызывает конструктор', static function () use ($hydrator): int {
    ConstructorCounter::$calls = 0;
    try {
        $hydrator->hydrate(['id' => []], CountedDto::class);
    } catch (HydrationException) {
        return ConstructorCounter::$calls;
    }

    return -1;
}, $accepted(0));

// AS-4: missing/null, доступные расширения и shape списков.
$probe('AS4-missing-default', 'Отсутствие optional-поля использует default null',
    static fn (): ?int => $hydrator->hydrate([], NullCastDto::class)->count, $accepted(null));
$probe('AS4-null-cast', 'Явный null пропускает property-cast', static function () use ($hydrator): array {
    ThrowOnNullCast::$calls = 0;
    $dto = $hydrator->hydrate(['count' => null], NullCastDto::class);

    return [$dto->count, ThrowOnNullCast::$calls];
}, $accepted([null, 0]));
$probe('AS4-required-nullable', 'Обязательный nullable-параметр не становится optional',
    static fn (): object => $hydrator->hydrate([], RequiredNullableDto::class),
    ['ok' => false, 'reason' => 'required_field_missing', 'path' => 'count']);
$probe('AS4-required-null', 'Обязательный nullable-параметр принимает явный null',
    static fn (): ?int => $hydrator->hydrate(['count' => null], RequiredNullableDto::class)->count, $accepted(null));
$probe('AS4-provider-missing', 'DefaultValue.when=Null не мешает constructor default',
    static fn (): ?int => $hydrator->hydrate([], NullProviderDto::class)->count, $accepted(null));
$probe('AS4-provider-value', 'DefaultValue.when=Null не мешает значению',
    static fn (): int => $hydrator->hydrate(['count' => 7], NullProviderDto::class)->count, $accepted(7));
$probe('AS4-provider-null', 'DefaultValue provider может отказать null, но автоматически не получает путь поля',
    static fn (): object => $hydrator->hydrate(['count' => null], NullProviderDto::class),
    ['ok' => false, 'reason' => 'explicit_null_not_allowed', 'path' => '']);
$probe('AS4-provider-pipeline', 'При отказе provider сохраняется unwrap, но имя поля отсутствует',
    static function () use ($send): array {
        [$result, $calls] = $send(NullProviderDto::class, ['data' => ['count' => null]], 'data');

        return [$result->errors->first()->code->value, $result->exception->path, $result->response->status, $calls];
    }, $accepted(['hydration_error', 'data', 200, 1]));
foreach (['empty' => [], 'list' => [['count' => 7]], 'map' => ['x' => ['count' => 7]], 'sparse' => [2 => ['count' => 7]]] as $shape => $items) {
    $probe('AS4-nested-' . $shape, 'Nested принимает и переиндексирует форму ' . $shape,
        static fn (): array => array_keys($hydrator->hydrate(['items' => $items], ListDto::class)->items),
        $accepted($items === [] ? [] : [0]));
    $probe('AS4-cast-' . $shape, 'Cast проверяет форму до штатной hydrateCollection: ' . $shape,
        static fn (): array => array_keys($hydrator->hydrate(['items' => $items], StrictListDto::class)->items),
        array_is_list($items) ? $accepted(array_keys($items)) : ['ok' => false, 'reason' => 'invalid_list_shape', 'path' => 'items']);
}
$probe('AS4-cast-nested-combination', 'Nested обходит property Cast на том же поле',
    static fn (): array => array_keys($hydrator->hydrate(['items' => ['x' => ['count' => 7]]], CombinedListDto::class)->items),
    $accepted([0]));
$probe('AS4-list-missing', 'Обязательный array-list отсутствовать не может',
    static fn (): object => $hydrator->hydrate([], ListDto::class),
    ['ok' => false, 'reason' => 'required_field_missing', 'path' => 'items']);
$probe('AS4-typed-missing', 'Обязательная typed collection автоматически становится пустой',
    static fn (): int => $hydrator->hydrate([], RequiredTypedDto::class)->items->count(), $accepted(0));
$probe('AS4-typed-guard', 'DefaultValue provider позволяет запретить autodefault, но путь пуст',
    static fn (): object => $hydrator->hydrate([], GuardedTypedDto::class),
    ['ok' => false, 'reason' => 'required_field_missing', 'path' => '']);

$failures = array_values(array_filter($observations, static fn (array $item): bool => !$item['matches']));
$report = [
    'php' => PHP_VERSION,
    'purpose' => 'Воспроизведение текущих возможностей и ограничений; это не приёмка будущих требований.',
    'count' => count($observations),
    'mismatches' => count($failures),
    'observations' => $observations,
];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
exit($failures === [] ? 0 : 1);
