# Атрибуты

## Механизм атрибутов

### AttributeRegistry
Сервис для регистрации связки атрибут → обработчик. Использует resolver для DI. Позволяет разработчику создавать кастомные атрибуты с автоматической обработкой.

### AttributeScanner
Внутренний компонент для сканирования классов через Reflection. Извлекает атрибуты с классов и свойств.

### AttributeMetadataCache
Кеш результатов сканирования классов. Оптимизация — Reflection выполняется один раз.

### AttributeMetadataCacheProviderInterface
Интерфейс клиента, предоставляющего общий `AttributeMetadataCache` для компонентов SDK. Реализуется `AbstractClient`.

### AttributeContext
Контекст для обработчика атрибута. Содержит инстанс атрибута, цель (класс/свойство), тип контекста (Request/DTO), этап pipeline, данные для модификации.

### AttributeContextType
Namespace: `Brahmic\ApiSutra\Enums\Attributes\AttributeContextType`.
Enum типа контекста атрибута: Request или Dto. Используется в AttributeContext.

### AttributeProcessor
Оркестратор обработки атрибутов. Сканирует класс, ищет обработчики, вызывает их с контекстом.

### AttributeHandlerInterface
Интерфейс обработчика кастомного атрибута. Метод handle получает AttributeContext.

## Атрибуты запросов

### Get, Post, Put, Delete
Атрибуты HTTP-методов. Указывают endpoint и метод запроса.

### Returns
Атрибут, указывающий класс DTO для десериализации ответа.

### Path, Query, Body, Header, Ignore
Атрибуты для маппинга свойств запроса в HTTP. Path — в URL path, Query — в query string, Body — в тело (параметр nested для вложенной структуры), Header — в заголовок, Ignore — не отправлять. По умолчанию (convention): {placeholder} → path, GET → query, POST → body, private → ignore. Атрибуты поддерживают кастомные имена (#[Query('custom')]) и параметры (arrayFormat, nullable, nested).

### Validate (атрибут)
Атрибут для валидации свойств запроса перед отправкой. Принимает строку Laravel validation rules и опциональный message. Пример: #[Validate('required|regex:/^[а-яёА-ЯЁ]+/', message: 'Только кириллица')]. SDK собирает правила, вызывает Validator, при ошибке — не отправляет запрос.

### Label (атрибут)
Атрибут для человекочитаемого имени поля в сообщениях валидации. Пример: #[Label('Фамилия')]. Используется с плейсхолдером :field в сообщениях.

### About (атрибут)
Атрибут для бизнес-описания DTO-поля в документации, анализе и export tooling.
Минимально требует только `title`; остальные поля (`description`, `example`,
`examples`, `format`, `nullableReason`, `note`) опциональны.

`About` не описывает техническую схему поля: тип, nullable, enum, nested DTO,
external field name и casts извлекаются из PHP-типа и data-transfer атрибутов.

Общее правило заполнения: не придумывать значения. Optional-поля заполняются только
по явному контракту провайдера, документации, пользовательскому описанию или
проверенному анализу. Если данных недостаточно, поле остаётся `null`.

`example` может быть scalar, JSON-строкой или `array`. Строка остаётся буквальным
примером и не должна автоматически парситься как JSON.

### ValidationError
Value Object ошибки валидации. Содержит: field (имя поля), rule (правило), message (сообщение), input (переданное значение).

### validationMessages()
Статический метод в классе запроса для кастомных сообщений валидации. Возвращает массив ['rule' => 'message']. Приоритет: атрибут message > метод > дефолты Laravel.

### BeforeSend, AfterResponse, BeforeHydrate, AfterHydrate (атрибуты)
Атрибуты для подключения переиспользуемых классов-обработчиков к хукам жизненного цикла. Принимают класс обработчика.

### QueryArrayFormat
Namespace: `Brahmic\ApiSutra\Enums\Http\QueryArrayFormat`.
Enum форматов сериализации массивов в query string. Brackets — `ids[]=1&ids[]=2`, Indices — `ids[0]=1&ids[1]=2`, Comma — `ids=1,2`, Repeat — `ids=1&ids=2`. Настраивается в ClientConfig (default) или на свойстве (#[Query(arrayFormat: ...)]).

### serializeNulls
Параметр клиентского request-level конфига. Определяет отправлять ли `null`-значения для request/query поведения. Для DX DTO null policy рекомендуется через `DtoSerializationProfile`, а для wire body — через `wireBodySerializationPolicy`. Переопределяется на свойстве через `#[Query(nullable: true)]`.
