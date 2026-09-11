# Механизм атрибутов

## Обзор

SDK использует PHP атрибуты для декларативной конфигурации запросов и DTO. Механизм поддерживает как встроенные атрибуты SDK, так и кастомные атрибуты разработчика.

## Контексты и уровни

**Контексты:**
- Request — атрибуты влияют на выполнение запроса
- DTO — атрибуты влияют на гидрацию

**Уровни:**
- На классе — применяется ко всему классу
- На свойстве — применяется к конкретному полю

## Встроенные атрибуты

### На Request

| Атрибут | Уровень | Назначение |
|---------|---------|-----------|
| `#[Get]`, `#[Post]`, etc. | Класс | HTTP метод и путь |
| `#[Returns]` | Класс | Response DTO |
| `#[NoAuth]` | Класс | Без аутентификации |
| `#[Cache]` | Класс | Настройки кеширования |
| `#[Retry]` | Класс | Настройки retry |
| `#[RateLimit]` | Класс | Настройки rate limit |
| `#[Timeout]` | Класс | Таймаут запроса |
| `#[Execution]` | Класс | Режим выполнения (Composite; DependsOn — всегда Sequential) |

### На DTO

| Атрибут | Уровень | Назначение |
|---------|---------|-----------|
| `#[Nested]` | Свойство | Гидрация вложенных объектов |

## Кастомные атрибуты

Разработчик может создавать свои атрибуты и регистрировать обработчики для них.

### Два типа

**Маркеры** — метаданные без автоматической обработки. Читаются в хуках вручную.

**С поведением** — регистрируется обработчик, SDK вызывает автоматически.

## Компоненты механизма

### AttributeRegistry

Регистрация связки "атрибут → обработчик":

```php
$attributes = new AttributeRegistry(
    resolver: fn(string $class) => app($class),
);

$attributes->register(
    MyCustomAttribute::class,
    MyCustomHandler::class,
);
```

### AttributeScanner

Внутренний компонент. Сканирует класс через Reflection, извлекает атрибуты с классов и свойств.

### AttributeMetadataCache

Кеширование результатов сканирования. Reflection дорогая операция — результат кешируется по имени класса.
Registry и Scanner используют общий кеш, который применяется и в сериализации,
и в гидрации.

Источник кеша — клиент:
`AbstractClient` реализует `AttributeMetadataCacheProviderInterface` и предоставляет
`getAttributeMetadataCache()`, чтобы все компоненты использовали единый кеш.

### AttributeContext

Контекст, передаваемый в обработчик:

- Инстанс атрибута (с параметрами)
- Цель: класс или свойство
- Тип контекста: Request или DTO
- Этап pipeline
- Данные для модификации
- Доступ к другим атрибутам класса
- Ссылка на `PipelineContext` (если обработка выполняется внутри pipeline)

### AttributeProcessor

Оркестратор:
1. Сканирует класс (через Scanner)
2. Для каждого атрибута ищет обработчик (через Registry)
3. Вызывает обработчик с контекстом

### AttributeHandlerInterface

```php
interface AttributeHandlerInterface
{
    public function handle(AttributeContext $ctx): mixed;
}
```

## Порядок выполнения

1. Встроенные атрибуты SDK
2. Кастомные атрибуты (в порядке регистрации)

Внутри класса — в порядке объявления в коде.

## Этапы pipeline

- **Validation‑атрибуты** (`#[Validate]`, `#[Label]`) — этап Validation.
- **Request‑атрибуты** (`#[Path]`, `#[Query]`, `#[Body]`, `#[Header]`, `#[File]`)
  — этап Prepare/Serialization.
- **Hook‑атрибуты** — на своих этапах (`beforeSend/afterResponse/beforeHydrate/afterHydrate`).
- **DTO‑атрибуты** (`#[From]`, `#[Nested]`, `#[Cast]`) — этап Hydration.

## Возможности обработчика

- Модифицировать данные
- Прервать выполнение (исключение или специальный Result)
- Получить доступ к другим атрибутам (композиция)

## Валидация

При первом сканировании класса проверяется:
- Атрибут применён на правильном уровне (класс/свойство)
- Параметры атрибута корректны
- Нет несовместимых комбинаций

Ошибки — понятные сообщения при инициализации.

## Связь с хуками

**Атрибуты** — декларативная конфигурация, SDK интерпретирует.

**Хуки** — императивная логика, код выполняется.

Дополняют друг друга:
- Атрибут-маркер можно прочитать в хуке
- Атрибут с обработчиком выполняется автоматически

## Пример кастомного атрибута

### Создание атрибута

```php
#[Attribute(Attribute::TARGET_CLASS)]
class LogSensitive
{
    public function __construct(
        public bool $maskBody = true,
    ) {}
}
```

### Создание обработчика

```php
class LogSensitiveHandler implements AttributeHandlerInterface
{
    public function handle(AttributeContext $ctx): mixed
    {
        // $ctx->attribute — инстанс LogSensitive
        // $ctx->target — ReflectionClass
        // $ctx->data — данные для модификации
        
        if ($ctx->attribute->maskBody) {
            // маскировать чувствительные данные
        }
        
        return $ctx->data;
    }
}
```

### Регистрация

```php
$attributes->register(LogSensitive::class, LogSensitiveHandler::class);
```

### Использование

```php
#[Get('/users/{id}')]
#[LogSensitive(maskBody: true)]
class GetUser extends AbstractRequest {}
```
