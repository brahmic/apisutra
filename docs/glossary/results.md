# Результаты и ошибки

## Результаты и ответы

### ResultInterface
Базовый интерфейс для всех результатов SDK. Определяет методы проверки состояния: isSuccess(), isPartial(), isFailed(), hasData(), hasErrors(). Позволяет типизацию в сигнатурах, мокирование в тестах, создание декораторов.

### ResultHandle
Унифицированная обёртка результата выполнения запроса. Возвращается из send()/sendAsync(). Содержит ExecutionResult или PromiseInterface.

Ключевые методы:
- raw() → ExecutionResult
- resolved() → ResolvedResultInterface
- resolvedAsync() → PromiseInterface
- dataOrFail() → данные или исключение
- rawAsync() → PromiseInterface (для конкурентных сценариев)
- continuationToken()/continuationTokenOrFail()
- await()/awaitAs() для unified provider async-await

### ResolvedResultInterface
Контракт «приложенческого» результата. Оборачивает ExecutionResult и предоставляет стабильный API:
data(), isSuccess(), isPartial(), isFailed(), hasData(), hasErrors(), errors(), result(),
а также DX‑методы для ошибок: error(), errorViews(), errorCode(), errorMessage(), errorStatus(),
errorRetryable(), errorCategory().

Также содержит унифицированный DX для токена продолжения:
- continuationToken(): ?string
- continuationTokenOrFail(): string

### ResolvedResult
Дефолтная реализация ResolvedResultInterface. Оборачивает ExecutionResult и даёт удобные методы чтения ошибок.

### ContinuationTokenExtractorInterface
Контракт стратегии извлечения continuation token из ExecutionResult.
Подключается через `ClientConfig::continuationTokenExtractor`.
Ядро не знает формат payload провайдера: провайдерный SDK определяет extractor сам.

### ResultMetaExtractorInterface
Контракт стратегии извлечения provider envelope meta из `ExecutionResult`.
Подключается через `ClientConfig::resultMetaExtractor`.
Позволяет централизованно заполнять `ExecutionResult->meta` для обычных запросов,
чтобы не дублировать технические поля в endpoint DTO.

### ContinuationService
Сервис orchestration для token-only сценариев:
- `awaitByToken(token, sourceRequestClass)`
- `awaitByTokenAs(token, finalType)`

Доступен через `$client->continuation()`. При ручном создании конструктор принимает
клиент и его `Hydrator`. Сначала определяет готовность, затем преобразует Ready-payload;
ошибка DTO завершает ожидание. Полный контракт — в [гайде ожидания](../guides/provider-async-await.md).

### ContinuationStateResolverInterface

Определяет Pending/Ready/Failed методом `resolve(ExecutionResult, ContinuationContext)`.
Подключается классом в `ContinuationResult::stateResolver` или экземпляром в
`ClientConfig::continuationStateResolver`.

### ContinuationContext

Контекст определения готовности: финальный тип, путь unwrap, класс исходного запроса
и режим. Тип и класс могут отсутствовать в зависимости от входа ожидания.

### ContinuationState

Результат resolver: `pending()`, `ready($payload, $path)` или `failed()`.
Ready отделяет готовность провайдера от успешности гидратации DTO.

### ContinuationStatus

Enum состояний Pending, Ready и Failed, используемый в ContinuationState.

### FinalPathStateResolver

Встроенный resolver для непустого unwrap: существующее значение, отличное от null,
означает Ready; отсутствие пути или null — Pending. К корню ответа не откатывается.

### ContinuationMode

Sync оценивает стартовый ответ, Auto может продолжить polling, Async начинает с poll.
Порядок входов и критериев — в [гайде](../guides/provider-async-await.md).

### ContinuationAwaitOptions

Настройки ожидания. `maxAttempts` ограничивает число poll-запросов; стартовый ответ
может дополнительно учитываться в диагностическом счётчике `attempts`.

### ContinuationOutcome

Сохранённый итог ожидания: value, исходный Ready-payload, его path, lastResult и attempts.
Повторный `awaitAs()` с другим типом преобразует сохранённый payload тем же гидратором;
с тем же типом возвращает прежний объект.

### ResolvedResultFactoryInterface
Фабрика для создания ResolvedResultInterface. Позволяет клиенту подменять тип результата (например, MClientResult) через ClientConfig::resolvedResultFactory.

### ClientResponse
Value Object для клиентского ответа. Содержит status, headers и body. Формируется фабрикой ClientResponseFactoryInterface и возвращается методом Client::response().

### ClientResponseFactoryInterface
Контракт фабрики клиентского ответа. Дефолтная реализация — ClientResponseFactory.

### ClientError
Value Object для ошибок клиентского ответа. Содержит multi‑mapping: provider_code (HTTP статус провайдера по умолчанию), sdk_code (ErrorCode), client_code/app_code (по умолчанию null), message, context, nested. Используется в body ответа и в DX‑методах ResolvedResult.

### ClientErrorMapperInterface
Контракт стратегии маппинга RequestError → ClientError. Это «логика», которая решает, какие client_code/app_code присвоить и какой HTTP‑статус вернуть для набора ошибок.

### DefaultClientErrorMapper
Дефолтная стратегия маппинга ошибок. Повторяет базовую логику SDK: provider_code = HTTP статус, sdk_code = ErrorCode, client_code/app_code = null, статус ответа определяется по sdk‑коду.

### ClientErrorMapperAwareInterface
Контракт фабрики, принимающей mapper ошибок. Это «инъекция»: фабрика не обязана знать детали маппинга, но умеет принять стратегию. Если кастомная фабрика реализует этот интерфейс, SDK передаст errorMapper из ClientConfig автоматически.

### ClientErrorFactory
Фабрика, которая строит `ClientError` из `RequestError` через `ClientErrorMapperInterface`.
Используется в `ClientResponseFactory` и `ResolvedResult` для единых правил отображения ошибок.

### ErrorContextFactoryInterface
Контракт фабрики типизированного контекста ошибки. Возвращает VO на основе `ClientError`.
Используется в `ResolvedResult` для `errorContext()` и `errorContexts()`.

### SystemErrorContextKeys
Enum системных ключей error context: `traceId`, `httpStatus`, `requestClass`, `providerCode`.
Используйте, чтобы избежать «магических» строк.

### ExecutionResult
Базовый класс результата, implements ResultInterface. Содержит: data (DTO), status (ResultStatus), errors (ErrorCollection), validationErrors (array<ValidationError>), debug (DebugInfo), traceId (UUID), audit (коллекция PipelineEvent), meta (?ResultMeta), nested (?array<ExecutionResult>), response (?ProviderResponse). Методы: hasValidationErrors(), validationErrorFor(field), message() (краткое сообщение об ошибках), requestDebug(), requestDebugJson(). В `requestDebug()` может присутствовать блок `oneOf` с диагностикой контрактного выбора варианта. Родитель для PaginatedResult и BatchResult.

### PaginatedResult
Extends ExecutionResult. Результат массовой загрузки страниц. Дополнительные методы: items() — объединённые данные, pages() — вложенные результаты страниц, meta() — типизированная PaginationMeta.

### BatchResult
Extends ExecutionResult. Результат runtime batch. Дополнительные методы: results()/successful()/failed()/getByClass() возвращают ResultCollection, get(index) — по индексу, meta() — типизированная BatchMeta. Итоговый статус: SUCCESS только если все SUCCESS, FAILED только если все FAILED, иначе PARTIAL.

### PoolResult
Extends ExecutionResult. Результат pool‑выполнения. Методы results()/successful()/failed() возвращают ResultCollection. Итоговый статус: SUCCESS только если все SUCCESS, FAILED только если все FAILED, иначе PARTIAL.

### ResultMeta
Интерфейс для метаданных результата. Реализации: PaginationMeta, BatchMeta. Позволяет полиморфную работу с метой.
Также может использоваться для provider-specific envelope meta через `ResultMetaExtractorInterface`.

### ProviderResponse
Value Object, инкапсулирующий ответ от внешнего API. Содержит HTTP-статус, заголовки и тело ответа, а также удобные методы доступа к данным (например, json()). Отделяет данные поставщика от внутренней логики SDK.

### ResultStatus
Namespace: `Brahmic\ApiSutra\Enums\Result\ResultStatus`.
Enum статуса результата. SUCCESS — полный успех, все запросы выполнены. PARTIAL — частичный успех, есть данные и ошибки. FAILED — полный провал, данных нет.

## Ошибки

### RequestError
Value Object ошибки на уровне SDK. Содержит код ошибки из SDK-классификации, сообщение, ссылку на ProviderResponse и вложенные ошибки для composite и запросов с зависимостями.

Для `ErrorCode::RequestContractViolation` контекст ошибки стандартизирован:
`contract`, `discriminatorField`, `discriminatorValue`, `matchedVariant`,
`filledVariants`, `violations`.

### ErrorCollection
Типизированная коллекция ошибок. Предоставляет методы для фильтрации и поиска: по коду, по запросу, получение сообщений.

## Исключения

### SdkException
Базовый класс всех исключений SDK. Родитель для ControlFlowException и RequestException.

### ControlFlowException
Базовый класс внутренних исключений SDK. Используется для управления поведением SDK (retry, refresh token). Не предназначен для пользовательского кода — SDK обрабатывает внутренне.

### RetryableException
Extends ControlFlowException. Сигнал для SDK — нужен retry. Свойства: retryAfter (?int, секунды), maxAttempts (?int). Выбрасывается из getRequestException() когда провайдер требует повторной попытки.

### EarlyReturnException
Extends ControlFlowException. Позволяет прервать выполнение из hook и вернуть результат без HTTP вызова. Свойство: data (mixed). Используется для кастомного кеширования, mock-данных по условию.

### ConnectionException
Extends SdkException. Ошибки сети: DNS, таймаут соединения, недоступность хоста.

### RequestException
Extends SdkException. Базовый класс ошибок запроса. Содержит nullable ProviderResponse: при локальном
rate-limit HTTP-ответ отсутствует. См. [контракт](../guides/client-config/rate-limit.md).

### ApiException
Общий базовый класс ошибок API провайдера в конкретном SDK. Используется для доменных исключений, которые не сводятся напрямую к HTTP коду.

### ClientException
Extends RequestException. Ошибки клиента (4xx). Подклассы: UnauthorizedException (401), PaymentRequiredException (402), ForbiddenException (403), NotFoundException (404), RequestTimeoutException (408), UnprocessableEntityException (422), RateLimitException (HTTP 429 или локальное исчерпание квоты).

### ValidationException
Extends SdkException. Локальная валидация DTO/Request (не HTTP). Выбрасывается методом validate() при ошибках. Свойство: errors (array). Для интеграции с Laravel ValidationException — преобразуйте в Exception Handler.

### RefreshTokenException (future)
Extends ControlFlowException. Сигнал для SDK выполнить refresh токена и повторить запрос.

### Validator
Реализация ValidatorInterface. Метод check() возвращает ValidationResult.

### PaymentRequiredException
Extends ClientException. HTTP 402. API с платными лимитами, исчерпание квоты.

### RequestTimeoutException
Extends ClientException. HTTP 408. Серверный таймаут обработки запроса (отличается от connection timeout).

### UnprocessableEntityException
Extends ClientException. HTTP 422. Ошибка валидации на стороне API (не локальная). Используется когда API возвращает 422 с ошибками валидации.

### ServerException
Extends RequestException. Ошибки сервера (5xx). Подклассы: InternalServerException (500), BadGatewayException (502), ServiceUnavailableException (503), GatewayTimeoutException (504).

### GatewayTimeoutException
Extends ServerException. HTTP 504. Таймаут upstream-сервера при проксировании.

### ConfigurationException
Extends SdkException. Ошибки конфигурации SDK: неверный baseUrl, отсутствующий auth и т.д.

### ContinuationConfigurationException
Extends ConfigurationException. Ошибки конфигурации unified async-await:
невалидное объявление poll request, отсутствие или неверный критерий готовности.

### ContinuationAwaitException

Ошибка ожидания с `reason`, `attempts`, `lastResult` и исходной причиной в `previous`.
Включает отсутствие token у Pending и ошибку преобразования Ready-payload
(`final_hydration_failed`). Последний ответ доступен и без debug;
см. [обработку ошибок ожидания](../guides/provider-async-await.md).

### HydrationException

Ошибка формы или типа DTO с `reason` и DTO-путём `path`. При внешних правилах
добавляет исходный JSON Pointer `sourcePath`, `sourcePathKind` и `sourceCandidates`.
`context()` сохраняет точные пути; `logContext()` маскирует неизвестные ключи источника.
См. [диагностику гидратации](../guides/hydration-rules.md#диагностика-и-входы).

### TestingException
Extends SdkException. Базовый класс исключений тестирования. Подклассы: UnmockedRequestException, MissingFixtureException.

### UnmockedRequestException
Extends TestingException. Выбрасывается при вызове preventStrayRequests(), если запрос не имеет mock-ответа. Защита тестов от случайных реальных HTTP-вызовов.

### MissingFixtureException
Extends TestingException. Выбрасывается при MockConfig::throwOnMissingFixtures(), если fixture не найден и запись новых запрещена. Для CI-сред.

### throw()
Метод ExecutionResult. Выбрасывает exception если isFailed() === true. Для fail-fast сценариев. Возвращает $this для chaining если успех.

### hasRequestFailed()
Метод AbstractClient и AbstractRequest для переопределения. Определяет, когда считать запрос неудачным. По умолчанию — HTTP status >= 400. Влияет на result->isFailed(). Приоритет: Запрос → Клиент → SDK.

### shouldRetry()
Метод AbstractClient и AbstractRequest для переопределения. Сигнатура: `shouldRetry(ProviderResponse $response, int $attempt): bool`. Определяет, нужен ли retry на основе body ответа (не только HTTP кода). Приоритет: shouldRetry() → RetryableException → retryOn.

### getRequestException()
Метод AbstractClient и AbstractRequest для переопределения. Определяет тип exception при throw(). По умолчанию — стандартная иерархия по HTTP коду. Позволяет выбрасывать кастомные exceptions или RetryableException.

### DefaultRequestFailurePolicyTrait
Namespace: `Brahmic\ApiSutra\Traits\DefaultRequestFailurePolicyTrait`.
Трейт с дефолтными реализациями hasRequestFailed()/shouldRetry()/getRequestException(). Используется в AbstractClient и AbstractRequest для устранения дублирования, не меняя порядок приоритетов в ErrorPolicy.
