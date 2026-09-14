# Контракт готовности continuation и доставки ошибок await

- Дата создания: 2026-09-14
- Дата обновления: 2026-09-14

Основание — [план 030](pln-030-readme.md). Решение владельца пакета от 2026-09-14:
выбрать архитектурно самый чистый вариант без сохранения старой эвристики.
Готовность определяется только явным критерием; успех или ошибка гидратации
никогда не означают pending. Основания и отклонённые варианты — в
[ADR-001](../../adr/adr-001-continuation-readiness.md). Это ломающее изменение
alpha-версии; миграция описывается в changelog и руководствах.

Уточнения по [проверке готовности](../../discussion/dsc-005-declarative-dto-contracts/implementation-readiness-review.md):
resolver получает контекст ожидания вместо атрибута (IR-02), любая ошибка
преобразования Ready-payload оборачивается одинаково (IR-03), определена граница
`maxAttempts`.

## Состояние ответа

```php
namespace Brahmic\ApiSutra\Enums\Continuation;

enum ContinuationStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
```

```php
namespace Brahmic\ApiSutra\Continuation;

final readonly class ContinuationState
{
    public static function pending(): self;
    public static function ready(mixed $payload, ?string $path = null): self;
    public static function failed(): self;

    public ContinuationStatus $status;
    public mixed $payload;   // только для Ready
    public ?string $path;    // DTO-путь payload в ответе для диагностики, как Returns::unwrap
}
```

Failed означает терминальное состояние протокола: ожидание прекращается, даже если
в ответе есть token.

## Контекст ожидания

```php
namespace Brahmic\ApiSutra\Continuation;

final readonly class ContinuationContext
{
    public function __construct(
        public ?string $finalType,          // null — финал возвращается без гидратации
        public ?string $unwrap,             // путь финала из декларации или null
        public ?string $sourceRequestClass, // null для awaitByTokenAs
        public ContinuationMode $mode,
    ) {}
}
```

Контекст строится ядром один раз на ожидание:

| Вход | finalType | unwrap | sourceRequestClass | mode |
| --- | --- | --- | --- | --- |
| `await()` / `awaitFromStartResult()`, запрос с `ContinuationResult` | override ?? `finalType` атрибута | `unwrap` атрибута | класс запроса | runtime override → `defaultMode` атрибута → `ClientConfig` |
| `awaitAs($type)` до первого ожидания | `$type` | `unwrap` атрибута или null | класс запроса | как выше |
| `await()` / `awaitAs()`, запрос без `ContinuationResult` | override или null | null | класс запроса | runtime override → `ClientConfig` |
| `awaitByToken($token, $sourceRequestClass)` | `finalType` атрибута | `unwrap` атрибута | переданный класс | Async |
| `awaitByTokenAs($token, $finalType)` | `$finalType` | null | null | Async |

`awaitByToken()` по-прежнему требует `ContinuationResult` у переданного класса.

## Критерий готовности

```php
namespace Brahmic\ApiSutra\Contracts\Interfaces\Continuation;

interface ContinuationStateResolverInterface
{
    public function resolve(ExecutionResult $result, ContinuationContext $context): ContinuationState;
}
```

Resolver не выполняет I/O, не гидратирует финальный тип и не вызывает конструкторы DTO.
Его исключения не превращаются в Pending и выходят без обёртки.

### Встроенный FinalPathStateResolver

`Brahmic\ApiSutra\Continuation\FinalPathStateResolver` читает `$result->response?->json()`
и путь `$context->unwrap`:

| Значение по пути | Состояние |
| --- | --- |
| Присутствует и не null | `ready(payload: значение, path: unwrap)` |
| Отсутствует, null, ответа нет или тело не JSON-объект | `pending()` |

Без непустого `$context->unwrap` встроенный resolver даёт `ContinuationConfigurationException`.
Он никогда не возвращает Failed: терминальные состояния конкретного протокола
определяет собственный resolver SDK. Корень ответа не используется как запасной финал.

### Выбор resolver

Для каждого ожидания в порядке:

1. `ContinuationResult::stateResolver` исходного запроса — class-string, создаётся без аргументов.
2. Непустой `$context->unwrap` — `FinalPathStateResolver`.
3. `ClientConfig::continuationStateResolver` — готовый экземпляр.
4. Иначе `ContinuationConfigurationException` до отправки первого poll request.

Недоступный класс или класс без интерфейса — `ContinuationConfigurationException`.

Изменения публичных сигнатур:

```php
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ContinuationResult
{
    public function __construct(
        public string $finalType,
        public ?string $unwrap = null,       // путь финала и критерий его присутствия
        public ?string $pollRequest = null,
        public ?ContinuationMode $defaultMode = null,
        public ?string $stateResolver = null, // class-string<ContinuationStateResolverInterface>
    ) {}
}

// ClientConfig: новый последний параметр, переносится в with()
public ?ContinuationStateResolverInterface $continuationStateResolver = null,
```

## Алгоритм ожидания

Попытка — оценка одного `ExecutionResult`: стартового или ответа poll request.

| Режим | Стартовый результат |
| --- | --- |
| Sync | Ready → финал; Pending → `ContinuationAwaitException(final_not_ready)`; Failed → доставка ошибки |
| Auto | Ready → финал; Pending → polling по token; Failed → доставка ошибки |
| Async | Готовность не оценивается; сразу polling по token из стартового результата |

Для каждой polling-попытки:

| Состояние | Действие |
| --- | --- |
| Ready | Финал |
| Failed | Доставка ошибки |
| Pending, token есть | Следующая попытка после `intervalMs`; после `maxAttempts` — `ContinuationAwaitException(attempts_exhausted)` |
| Pending, token нет | Если результат failed — `$result->throw()`; иначе `ContinuationAwaitException(continuation_token_missing)` |

- **`maxAttempts`** — максимальное число poll-запросов, как сейчас. Стартовый результат
  попыток не расходует. Pending-ответ последнего разрешённого poll-запроса завершает ожидание
  без следующего запроса; пауза после него не выполняется.
- **Доставка ошибки:** failed-результат бросает своё исключение через
  `$result->throw()`, типизированные ошибки провайдера не оборачиваются. Нефейловый
  результат в состоянии Failed даёт `ContinuationAwaitException(continuation_failed)`.
- Token по-прежнему извлекается только настроенным extractor. Если token нужен,
  а extractor не настроен, — `ContinuationConfigurationException`.
- Failed HTTP/provider-ответ с token и состоянием Pending продолжает ожидание:
  pending протокола отличает resolver, а не статус результата.
- Контракт poll request (ровно один обязательный scalar-параметр) не меняется.

### Финал

1. `finalType` null — возвращается payload без преобразования.
2. Иначе payload преобразуется в `finalType` гидратором клиента. Payload, который не является
   массивом или объектом, даёт `HydrationException(unexpected_response_shape)` без вызова
   гидратора.
3. **Любая** `HydrationException` этого преобразования — проверки формы или гидратора —
   получает префикс `path` через `prependPath` (для формы — путь `path` или `$`)
   и оборачивается в `ContinuationAwaitException(final_hydration_failed)`: исходная ошибка —
   в previous, `attempts` и `lastResult` — текущего ожидания.
4. Ошибки конфигурации DTO (`ConfigurationException`) выходят без обёртки.

То же правило действует для `hydrateOutcome()` (кешированный `awaitAs()`), где `attempts`
и `lastResult` берутся из сохранённого outcome.

## ContinuationAwaitException

```php
namespace Brahmic\ApiSutra\Exceptions\Continuation;

final class ContinuationAwaitException extends SdkException
{
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly int $attempts,
        public readonly ExecutionResult $lastResult,
        ?Throwable $previous = null,
    );

    /** @return array<string, mixed> */
    public function context(): array;
}
```

| reason | Когда |
| --- | --- |
| `final_hydration_failed` | Ready-payload или сохранённый payload не преобразуется в финальный тип, включая неверную форму |
| `final_not_ready` | Sync, стартовый результат Pending |
| `continuation_token_missing` | Pending без token у нефейлового результата |
| `attempts_exhausted` | Pending после последнего разрешённого poll-запроса |
| `continuation_failed` | Resolver вернул Failed для нефейлового результата |

- `attempts` — число оценённых результатов: стартовый в Sync и Auto плюс ответы poll-запросов.
  Например, Auto с `maxAttempts: 1`: старт Pending и один Pending poll — один poll-запрос,
  `attempts = 2`; Async с `maxAttempts: 1` — один poll-запрос, `attempts = 1`.
- `lastResult` — результат последней оценённой попытки, с HTTP-ответом.
- `context()` содержит `reason`, `attempts`, `httpStatus` последнего ответа, `traceId`
  и для `final_hydration_failed` — `hydration` с `HydrationException::context()`.
- Message и context не содержат payload, token и значения полей.

## Гидратор и объекты ожидания

- `ContinuationService::__construct(ClientInterface $client, Hydrator $hydrator)` —
  гидратор обязателен. `AbstractClient` передаёт свой экземпляр после завершения 031.
  Сторонняя реализация `ClientInterface` передаёт гидратор явно, например `Hydrator::default()`.
- `ContinuationService` — единственное место гидратации финала.
  `ResultHandle` больше не вызывает `Hydrator::default()`; его конструктор не меняется.
- Новый результат ожидания:

```php
namespace Brahmic\ApiSutra\Continuation;

final readonly class ContinuationOutcome
{
    public mixed $value;               // финал в запрошенном типе или payload при finalType null
    public mixed $payload;             // исходный Ready-payload до гидратации
    public ?string $path;
    public ExecutionResult $lastResult;
    public int $attempts;
}
```

- Публичные методы `ContinuationService`:
  - `resolveFromStartResult(ExecutionResult $startResult, ?RequestInterface $sourceRequest = null, ?string $finalTypeOverride = null, ?ContinuationAwaitOptions $options = null): ContinuationOutcome`;
  - `hydrateOutcome(ContinuationOutcome $outcome, string $finalType): ContinuationOutcome`;
  - `awaitFromStartResult()`, `awaitByToken()`, `awaitByTokenAs()` сохраняют сигнатуры
    и возвращают `value`.
- `ResultHandle` хранит последний `ContinuationOutcome`:
  - повторный `await()` и `awaitAs()` с тем же типом возвращают кешированное значение без запросов;
  - `awaitAs()` с другим типом гидратирует сохранённый `payload`, а не ранее созданный DTO,
    без polling;
  - при ошибке преобразования действует правило раздела «Финал»; кешированный outcome
    не заменяется.

## Ошибки конфигурации

`ContinuationConfigurationException` сохраняется для: отсутствующего poll request,
пустого token, неверного контракта poll request, отсутствия критерия готовности,
неверного `stateResolver`, `FinalPathStateResolver` без `unwrap`, отсутствия extractor
при необходимости token, `ResultHandle` без клиента, `awaitByToken()` без
`ContinuationResult` и отсутствующего класса `finalType`.

## Изменения поведения

| Было | Стало |
| --- | --- |
| Любое исключение гидратации означает «ещё не готово» | Гидратация выполняется только для Ready; ошибка — `final_hydration_failed` |
| `unwrap` необязателен, отсутствие/null пути возвращают весь payload | `unwrap` — путь финала и критерий присутствия; без него нужен resolver |
| DTO с defaults завершает ожидание на промежуточном ответе | Промежуточный ответ остаётся Pending |
| Sync без финала: `ContinuationConfigurationException` | `ContinuationAwaitException(final_not_ready)` |
| Лимит попыток и отсутствие token: configuration-ошибка | `ContinuationAwaitException` с reason, attempts и последним результатом |
| Cached `awaitAs`: `ContinuationConfigurationException` без previous | `ContinuationAwaitException` с previous и `lastResult` |
| `new ContinuationService($client)` | `new ContinuationService($client, $hydrator)` |
| Декларация без `unwrap` и resolver работала | `ContinuationConfigurationException` до polling |

## Зависимости

- Передача гидратора клиента в `ContinuationService` выполняется после завершения 031.
- 030 не вводит внешние правила 028. После 030 правила 028 подключаются к тому же
  гидратору; особого режима совместимости для strict-ошибок не требуется.

## Документация

Обновить `docs/guides/provider-async-await.md`, `docs/guides/continuation-token.md`,
`docs/guides/attributes/response.md` (`ContinuationResult`), `docs/guides/errors.md`
(`ContinuationAwaitException`) и `CHANEGLOG.md` с разделом миграции: объявить `unwrap`
или resolver, заменить обработку `ContinuationConfigurationException` на runtime-ошибки ожидания.
