# План рефакторинга Serializer.php

## Финальное ожидание

После рефакторинга слой сериализации состоит из:
- **Serializer** — тонкий оркестратор, который собирает `PreparedRequest` из частей, делегируя работу хелперам
- **ReflectionHelperTrait** — общие методы работы с рефлексией (`getAttribute`, `getPrimaryType`), устраняет дублирование в 3 классах
- **NamingStrategyResolver** — единый сервис преобразования имён, устраняет дублирование `applyNamingStrategy` + `toSnakeCase` в 3 классах
- **PropertyMeta** — readonly VO вместо ассоциативного массива метаданных, с методом `shouldSkip()`
- **ValueSerializer** — общая логика сериализации значений (cast-реестр, DateTime, Enum, DTO, JsonSerializable) — **опционально** и только после подтверждения отсутствия регрессий
- Все `match`/`array_find` вместо if/elseif/foreach по стилю PHP 8.4
- **Serializer** — оркестратор + набор мелких сервисов для сборки частей запроса (опционально, без изменения публичного API)
- Все тесты проходят без изменений

---

## Принципы безопасного рефакторинга

- Минимальная версия PHP 8.4 подтверждена; property hooks допустимы там, где это разрешено синтаксисом (для enum/readonly классов используем методы)
- `AttributeMetadataCache` хранит данные in-memory — `PropertyMeta` с `ReflectionProperty` безопасен (если стратегия кэша изменится, пересмотреть)
- `DtoSerializer` уже отрефакторен — использовать как эталон поведения; изменять только ради общих хелперов
- Чтение свойств DTO должно быть безопасным для private/protected и неинициализированных значений (без фаталов)
- Сериализация DTO ограничена только публичными свойствами
- Любой шаг — минимальный дифф, без изменения публичных сигнатур
- После каждого шага — прогон `pest tests/Unit/Serialization/`, после группы шагов — повторный прогон всей ветки сериализации и пайплайна

---

## Граф зависимостей (до рефакторинга)

```
AbstractClient
├── new Hydrator(casts, cache)
├── new Serializer(casts, cache)
└── Pipeline
    ├── PreparedRequestFactory ← Serializer
    ├── ResponseHydrator ← Hydrator
    └── CompositeFlow ← Hydrator

Serializer ──lazy──► DtoSerializer

AbstractDto
├── Hydrator::default() → from()
└── DtoSerializer::default() → toArray()
```

**Важно**: `Serializer`, `DtoSerializer`, `Hydrator` — `final` классы, не зарегистрированы в DI-контейнере, создаются напрямую через `new`. Изменения конструкторов затрагивают: `AbstractClient`, `Pipeline`, `PreparedRequestFactory`, тесты.

---

## Шаги

### Шаг 0: Базовая проверка и контроль изменений

**Что**: зафиксировать baseline и ограничения среды.

**Действия**:
- Подтвердить, что `AttributeMetadataCache` in-memory и не сериализует данные
- Прогнать `pest tests/Unit/Serialization/` и сохранить результат как baseline

**Нюансы**:
- Если стратегия кэша изменится на персистентную — отказаться от `PropertyMeta` или убрать `ReflectionProperty` из VO

---

### Шаг 1: ReflectionHelperTrait

**Что**: выделить 2 идентичных метода из 3 классов в trait.

**Методы**:
- `getAttribute(ReflectionProperty, string): ?object` — одинаков в Serializer:396, DtoSerializer:230, Hydrator:342
- `getPrimaryType(ReflectionProperty): ?string` — одинаков в Serializer:406, DtoSerializer:240, Hydrator:352

**Файл**: `src/Serialization/Concerns/ReflectionHelperTrait.php`

**PHP 8.4**: в `getPrimaryType` заменить foreach на `array_find`:
```php
if ($type instanceof ReflectionUnionType) {
    return array_find(
        $type->getTypes(),
        static fn (ReflectionNamedType $t) => $t->getName() !== 'null',
    )?->getName();
}
```

**Нюансы**:
- Trait подключается к 3 final-классам — не ломает наследование, т.к. наследников нет
- Метод `getAttribute` возвращает `?object`, а не конкретный тип атрибута — это осознанно, т.к. атрибуты разных типов
- Удалить приватные копии из Serializer, DtoSerializer, Hydrator
- Тесты: не требуют изменений, т.к. методы приватные и вызываются через публичные API

**Проверка**: запустить `pest tests/Unit/Serialization/`

---

### Шаг 2: NamingStrategyResolver

**Что**: выделить дублированную логику именования из 3 классов в отдельный final-сервис.

**Методы**:
- `resolve(string $name, ?PipelineContext $context): string`
- `private toSnakeCase(string $value): string`

**Файл**: `src/Serialization/NamingStrategyResolver.php`

**Дублирование**:
- `applyNamingStrategy` — одинаков в Serializer:378, DtoSerializer:207, Hydrator:426
- `toSnakeCase` — одинаков в Serializer:390, DtoSerializer:224, Hydrator:438

**Интеграция**:
- Создать один экземпляр `NamingStrategyResolver` и передавать/создавать в 3 классах
- Минимальное решение — статический метод или readonly-класс без зависимостей:
```php
final readonly class NamingStrategyResolver
{
    public function resolve(string $name, ?PipelineContext $context): string
    {
        if ($context === null) {
            return $name;
        }

        return match ($context->config->namingStrategy) {
            NamingStrategy::SnakeCase => $this->toSnakeCase($name),
            NamingStrategy::None => $name,
        };
    }

    private function toSnakeCase(string $value): string
    {
        $result = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;
        return strtolower($result);
    }
}
```

**Нюансы**:
- Класс без зависимостей — можно создать `new NamingStrategyResolver()` внутри конструкторов без изменения публичных сигнатур
- Не меняет внешний API: конструкторы Serializer, DtoSerializer, Hydrator остаются прежними
- Удалить `applyNamingStrategy` и `toSnakeCase` из 3 классов
- Тесты: не требуют изменений

**Проверка**: запустить `pest tests/Unit/Serialization/`

---

### Шаг 3: PropertyMeta VO

**Что**: заменить ассоциативный массив из `getPropertyMetadata()` на readonly VO.

**Файл**: `src/Serialization/VO/PropertyMeta.php`

```php
readonly class PropertyMeta
{
    public function __construct(
        public string $name,
        public ReflectionProperty $property,
        public bool $isPublic,
        public bool $isStatic,
        public ?Ignore $ignore,
        public ?Path $path,
        public ?Query $query,
        public ?Body $body,
        public ?Header $header,
        public ?File $file,
        public ?CastAttribute $cast,
    ) {}

    /** Свойство пропускается при сериализации */
    public function shouldSkip(): bool
    {
        return $this->isStatic || $this->ignore !== null || !$this->isPublic;
    }
}
```

**Нюансы**:
- `getPropertyMetadata()` кэширует результат через `AttributeMetadataCache`
- VO содержит `ReflectionProperty` — объект не сериализуем, допустимо при in-memory кэше
- Заменить деструктуризацию `$metadata['name']`, `$metadata['isStatic']` на `$meta->name`, `$meta->shouldSkip()` в `serialize()`
- `shouldSkip()` заменяет строки 79–85 в Serializer::serialize()

**Проверка**: запустить `pest tests/Unit/Serialization/SerializerTest.php tests/Unit/Serialization/SerializerFilesTest.php`

---

### Шаг 4: Замена setByPath на ArrayPath::setByPath

**Что**: удалить `Serializer::setByPath()` (строки 513–526), заменить на существующий `ArrayPath::setByPath()`.

**Обоснование**: `DtoSerializer` уже использует `ArrayPath::setByPath()` (строка 68). Реализация идентична.

**Изменения**:
- Добавить `use Brahmic\ApiSutra\Support\ArrayPath;` (если нет)
- Заменить `$this->setByPath($body, $path, $value)` → `ArrayPath::setByPath($body, $path, $value)` (строка 128)
- Удалить приватный метод `setByPath`

**Нюансы**:
- `ArrayPath::setByPath` — статический метод, принимает `array &$data` по ссылке — поведение идентично
- Реализации побайтно совпадают (за исключением `self::segments()` vs `explode()` — в ArrayPath кэш сегментов, но это улучшение, не поломка)

**Проверка**: запустить `pest tests/Unit/Serialization/SerializerTest.php` — тест `сериализует DTO в nested body пути`

---

### Шаг 5: Декомпозиция Serializer::serialize()

**Что**: разбить метод на 4-5 приватных методов с чёткой ответственностью.

**Текущая структура** (145 строк, ~7 ответственностей):
1. Определение baseUrl (строки 51–56)
2. Сбор pagination overrides (строки 68–76)
3. Итерация по свойствам: распределение в query/body/headers/files/path (строки 78–153)
4. Сборка URL с path-параметрами и query string (строки 155–161)
5. Подготовка body (строка 163)
6. Обработка файловых форматов (строки 166–178)
7. Создание PreparedRequest (строки 180–194)

**Предлагаемая структура**:
```php
public function serialize(RequestInterface $request, ?PipelineContext $context = null): PreparedRequest
{
    $baseUrl = $this->resolveBaseUrl($request, $context);
    $paginationOverrides = $this->resolvePaginationOverrides($request, $context);
    $parts = $this->collectParts($request, $context, $paginationOverrides);
    $url = $this->buildFullUrl($baseUrl, $request->getEndpoint(), $parts);

    return $this->buildPreparedRequest($request, $url, $parts);
}
```

**Выделяемые методы**:
- `resolveBaseUrl(RequestInterface, ?PipelineContext): string` — строки 51–56
- `resolvePaginationOverrides(RequestInterface, ?PipelineContext): array` — строки 68–76
- `collectParts(RequestInterface, ?PipelineContext, array): SerializedParts` — строки 78–153 (VO для query/body/headers/files/fileFormat/placeholders)
- `buildFullUrl(string, string, SerializedParts): string` — строки 155–161
- `buildPreparedRequest(RequestInterface, string, SerializedParts): PreparedRequest` — строки 163–194

**Промежуточный VO** (опционально): `SerializedParts` — readonly VO для передачи собранных данных между методами. Альтернатива — передавать по отдельности, но VO чище.

**Нюансы**:
- Метод `collectParts` всё ещё будет ~70 строк — это нормально, т.к. это одна итерация по свойствам с маршрутизацией
- `buildPreparedRequest` включает switch по FileFormat — можно дополнительно выделить `prepareBody` (см. Шаг 6)
- Не менять порядок обработки свойств (files → headers → path → body/query) — это важно для логики fallback в query/body

**Проверка**: запустить все тесты `pest tests/Unit/Serialization/`

---

### Шаг 6: match вместо if/elseif в обработке файлов

**Что**: заменить цепочку `if/elseif` по `FileFormat` (строки 166–178) на `match`.

**Было**:
```php
if ($fileFormat === FileFormat::Multipart) { ... }
elseif ($fileFormat === FileFormat::Binary) { ... }
elseif ($fileFormat === FileFormat::Base64) { ... }
```

**Стало**: выделить в отдельный метод `prepareBodyAndStream`, использовать match:
```php
private function prepareBodyAndStream(
    ?FileFormat $fileFormat,
    array $files,
    array $body,
    array &$headers,
): array {
    return match ($fileFormat) {
        FileFormat::Multipart => $this->buildMultipartResult($files, $body, $headers),
        FileFormat::Binary    => $this->buildBinaryResult($files, $headers),
        FileFormat::Base64    => ['body' => json_encode($this->applyBase64Files($files, $body), JSON_UNESCAPED_UNICODE), 'stream' => null],
        null                  => ['body' => $body === [] ? null : json_encode($body, JSON_UNESCAPED_UNICODE), 'stream' => null],
    };
}
```

**Нюансы**:
- `match` требует exhaustive cases — `null` покрывает случай "нет файлов"
- `Multipart` мутирует `$headers` (добавляет Content-Type с boundary) — передавать по ссылке или возвращать headers в результате
- Каждый case — чистый вызов метода, побочных эффектов внутри match нет (кроме headers)

**Проверка**: запустить `pest tests/Unit/Serialization/SerializerFilesTest.php`

---

### Шаг 7: match в formatArrayQuery

**Что**: заменить if/continue-цепочку на `match` + `array_map`.

**Нюансы**:
- `QueryArrayFormat::Comma` — особый случай (одна строка vs. множество), обработать отдельно early return
- Оставшиеся 3 формата (Repeat, Indices, Brackets) — через match
- `array_values($values)` с индексами — использовать `array_map` с двумя массивами

**Проверка**: запустить `pest tests/Unit/Serialization/SerializerTest.php`

---

### Мини-этап: унифицировать Serializer::serializeValue под DtoSerializer

**Что**: привести структуру `Serializer::serializeValue()` к модели `DtoSerializer` без изменения поведения.

**Суть**:
- Вынести резолв кастов в `resolveCastByType` и `resolveCastByValue`
- Вынести fallback в `serializeObject`
- Не добавлять обработку массивов (в `Serializer` это отдельная логика)

**Нюансы**:
- Порядок важен: каст-атрибут → DTO → резолв каста → fallback
- Поведение массивов не менять, чтобы избежать регрессий

**Проверка**: запустить `pest tests/Unit/Serialization/SerializerTest.php`

---

### Шаг 8: isQueryMethod → метод в HttpMethod

**Что**: перенести логику `isQueryMethod` в enum `HttpMethod`.

**Файл**: `src/Enums/Http/HttpMethod.php`

```php
enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';

    /** HTTP-методы, передающие параметры через query string */
    public function isQueryMethod(): bool
    {
        return match ($this) {
            self::GET, self::DELETE => true,
            default => false,
        };
    }
}
```

**В Serializer**: заменить `$this->isQueryMethod($method)` → `$method->isQueryMethod()`, удалить приватный метод.

**Нюансы**:
- Property hook в enum — фича PHP 8.4, убедиться что минимальная версия пакета 8.4
- `HttpMethod` используется в других местах (AbstractRequest, тесты) — добавление свойства не ломает ничего
- Удалить `isQueryMethod` из Serializer

**Проверка**: запустить `pest tests/Unit/Serialization/SerializerTest.php`

---

### Архитектурный этап: декомпозиция Serializer в сервисы (опционально)

**Что**: вынести тяжёлые блоки `Serializer` в мелкие сервисы, не меняя публичный API и поведение.

**Предложение сервисов**:
- `RequestPartsCollector` — сбор `query/body/headers/files/fileFormat/placeholders`, включая `getPropertyMetadata()` и `serializeValue()`
- `RequestUrlBuilder` — `buildUrl()`, `applyPathParams()`, `buildQueryString()`, `formatArrayQuery()`
- `FilePayloadPreparer` — `prepareBodyAndStream()`, `prepareMultipartBody()`, `prepareBinaryBody()`, `buildMultipartStream()`, `applyBase64Files()`

**Нюансы**:
- `Serializer` остаётся оркестратором, сигнатуры публичных методов без изменений
- Зависимости передавать явно (без DI‑контейнера)
- Для `DtoSerializer` допускается ленивый резолвер (callable), чтобы не менять ленивую инициализацию
- Порядок обработки свойств сохранять (file → header → path → body/query)

**Проверка**: запустить `pest tests/Unit/Serialization/`

---

### Шаг 9: ValueSerializer (общая логика сериализации значений)

**Что**: выделить дублированный `serializeValue()` из `Serializer` и `DtoSerializer` в один сервис.
**Статус**: **опционально, последний шаг**. Делать только после прохождения всех тестов и сравнения поведения на реальных примерах.

**Файл**: `src/Serialization/ValueSerializer.php`

**Анализ различий**:

| Аспект | Serializer | DtoSerializer |
|---|---|---|
| Cast атрибут | ✅ | ✅ |
| Registry cast | ✅ | ✅ |
| DateTime fallback | ✅ | ✅ |
| Enum fallback | ✅ | ✅ |
| DtoInterface | → DtoSerializer | → self::serialize() |
| Array recursion | ❌ | ✅ serializeArray() |
| toArray/JsonSerializable | ✅ | ✅ |
| getPrimaryType перед DTO | ✅ вызывается | ✅ вызывается |

**Различие**: как обрабатывается `DtoInterface` — через делегирование разным сериализаторам. Решение — передавать callable для DTO-сериализации:

```php
final class ValueSerializer
{
    use ReflectionHelperTrait;

    public function __construct(
        private readonly CastRegistry $casts,
    ) {}

    /**
     * @param Closure(object, ?PipelineContext): mixed $dtoSerializer
     */
    public function serialize(
        mixed $value,
        ?CastAttribute $cast,
        ReflectionProperty $property,
        ?PipelineContext $context,
        Closure $dtoSerializer,
    ): mixed { ... }
}
```

**Нюансы**:
- `DtoSerializer` обрабатывает массивы рекурсивно (`serializeArray`) — эту логику оставить в `DtoSerializer`, а `ValueSerializer` вызывать для отдельных значений
- `Serializer.serializeValue` не обрабатывает массивы — разница минимальна
- Callable для DTO-сериализации позволяет избежать циклической зависимости
- Передавать `$dtoSerializer` при каждом вызове — можно закэшировать closure в конструкторе
- При малейших расхождениях поведения (особенно с DTO/array/null) — откатить шаг 9
- Учитывая текущую структуру `DtoSerializer` (разделённые `serializeBy*`), безопаснее начать с унификации **внутри** `Serializer`, а не выносить в общий сервис

**Альтернатива**: если callable кажется слишком сложным — оставить `serializeValue` в обоих классах, но вынести только общие ветки (cast, registry, DateTime, Enum, toArray/JsonSerializable) в общий метод trait. Проще, но менее DRY.

**Проверка**: запустить `pest tests/Unit/Serialization/`

---

### Шаг 10: Инжекция DtoSerializer вместо lazy-init

**Что**: убрать ленивую инициализацию `getDtoSerializer()`, создавать `DtoSerializer` сразу в конструкторе.
**Статус**: низкий риск, но можно оставить lazy-init ради минимального диффа.

**Было**:
```php
private ?DtoSerializer $dtoSerializer = null;

private function getDtoSerializer(): DtoSerializer
{
    if ($this->dtoSerializer === null) {
        $this->dtoSerializer = new DtoSerializer($this->casts, $this->cache);
    }
    return $this->dtoSerializer;
}
```

**Стало**:
```php
private readonly DtoSerializer $dtoSerializer;

public function __construct(
    private readonly CastRegistry $casts,
    private readonly ?AttributeMetadataCache $cache = null,
) {
    $this->dtoSerializer = new DtoSerializer($this->casts, $this->cache);
}
```

**Нюансы**:
- `DtoSerializer` лёгкий (нет внешних зависимостей, нет IO) — нет смысла в lazy init
- Конструктор `Serializer` не меняет сигнатуру — `AbstractClient` и тесты не затронуты
- Удалить `getDtoSerializer()`, заменить все `$this->getDtoSerializer()` на `$this->dtoSerializer`

**Проверка**: запустить `pest tests/Unit/Serialization/SerializerTest.php`

---

### Шаг 11: Типизация массивных параметров (docblocks)

**Что**: добавить `@param` docblock-типы к приватным методам с `array`.

**Методы**:
- `collectFiles(array &$files, ...)` → `@param array<int, array{name: string, file: FileInput}> $files`
- `buildMultipartStream(array $files, array $body)` → аналогично
- `applyBase64Files(array $files, array $body)` → аналогично
- `buildQueryString(array $query, ...)` → `@param array<string, array{value: mixed, format: ?QueryArrayFormat}> $query`
- `applyPathParams(string $endpoint, array $params)` → `@param array<string, mixed> $params`

**Нюансы**:
- Не менять сигнатуры — только docblocks
- Помогает IDE и статическому анализу (PHPStan/Psalm)

**Проверка**: не требуется (только документация)

---

## Порядок выполнения и зависимости

```
Шаг 0  (baseline + проверки)       ─── обязательный
Шаг 11 (docblocks)                 ─── независимый
Шаг 4  (ArrayPath::setByPath)      ─── независимый
Шаг 1  (ReflectionHelperTrait)     ─── независимый
Шаг 2  (NamingStrategyResolver)    ─── независимый
Шаг 8  (HttpMethod::isQueryMethod) ─── независимый
Шаг 10 (DtoSerializer inject)      ─── независимый (опционально)
Шаг 3  (PropertyMeta VO)           ─── независимый
─────────────────────────────────────────────────────
Шаг 5  (Декомпозиция serialize)    ─── после шагов 4, 8 (и 3, если он выполнен)
Шаг 6  (match FileFormat)          ─── после шага 5 (или одновременно)
Шаг 7  (match formatArrayQuery)    ─── независимый
Мини-этап (serializeValue)         ─── перед шагом 9
Арх-этап (сервисы Serializer)      ─── после стабилизации шагов 1–8
─────────────────────────────────────────────────────
Шаг 9  (ValueSerializer)           ─── последний и опциональный
```

**Рекомендация**: выполнять шаги последовательно (минимальные диффы), после каждого — прогон тестов.

**После каждого шага**: `pest tests/Unit/Serialization/` — полный прогон тестов сериализации.

---

## Чек-лист безопасности

- [ ] `AttributeMetadataCache` in-memory подтверждён
- [ ] `DtoSerializer::readProperty()` не падает на private/protected и uninitialized свойствах
- [ ] Публичные сигнатуры не менялись
- [ ] Не менять порядок обработки свойств в `serialize()` (files → headers → path → query/body)
- [ ] Не менять логику `shouldIncludeNull` — она разная в `Serializer` (принимает `?Query`) и `DtoSerializer` (принимает `?PipelineContext`) — **не объединять**
- [ ] `extractPathParams` — плейсхолдеры `{name}` инициализируются как `null` — это важно для обнаружения неподставленных параметров
- [ ] `paginationOverrides` подставляются через `??` — при `null` override используется оригинальное значение свойства
- [ ] `fileFormat` берётся из **последнего** обработанного `#[File]` атрибута — не менять это поведение при рефакторинге
- [ ] `DtoSerializer::default()` и `Hydrator::default()` — singleton-паттерн для `AbstractDto::from()/toArray()` — не трогать
- [ ] `meta` в `PreparedRequest` содержит `requestInstance` — живую ссылку на объект request, это используется downstream
- [ ] Тесты файлов (`SerializerFilesTest`) проверяют Content-Type header, boundary в multipart — не терять эти side effects
- [ ] `CastAttribute->class` инстанциируется через `new $cast->class(...)` — не менять механизм (dynamic class instantiation)
- [ ] В Hydrator `ReflectionProperty` хранится в кэше — объект не Serializable, но кэш in-memory — OK

---

## Не входит в рефакторинг

- Изменение публичных API (`serialize()`, `hydrate()`, конструкторы)
- Изменение поведения (порядок приоритетов, null-handling, naming strategy)
- Рефакторинг `Hydrator` (отдельная задача, аналогичная по структуре)
- Рефакторинг `ResponseHydrator`, `CompositeFlow`, `Pipeline`
- Добавление новых фич
