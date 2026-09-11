# Attributes (механизм)

Короткий обзор того, как работают атрибуты в SDK. Полный перечень атрибутов — в `docs/guides/attributes/*`.

## Роль атрибутов
Атрибуты — декларативный слой конфигурации запросов и DTO. Они позволяют описывать:
- HTTP‑метод и endpoint
- источники данных (query/body/path/header/file)
- правила валидации и трансформации
- поведение пайплайна (cache/retry/timeout/ratelimit)

## Где используются
- **RequestSpecResolver** — собирает метаданные запроса (HTTP, responseType, behavior).
- **Serializer** — применяет request‑атрибуты для сборки PreparedRequest.
- **Hydrator** — применяет DTO‑атрибуты при гидрации.
- **HookRunner** — исполняет hook‑атрибуты.
- **AttributeRegistry** — запускает кастомные обработчики атрибутов.

## Резолв и кеш
- Метаданные атрибутов читаются рефлексией.
- `AttributeMetadataCache` хранит результаты и снижает стоимость повторных вызовов.
- В `Environment::Local/Testing` кеш отключён, в Production включён.

## Кастомные атрибуты (AttributeRegistry)
`AttributeRegistry` регистрирует соответствия "атрибут → обработчик".
Обработчик может быть классом или инстансом.

### Типы обработчиков
- **AttributeHandlerInterface** — простой обработчик:
  получает атрибут, Reflection‑цель и `PipelineContext`.
- **AttributeContextHandlerInterface** — контекстный обработчик:
  получает `AttributeContext` с `PipelineStage`, `type`, `data` и списком
  атрибутов класса; может вернуть модифицированные данные.

### Порядок обхода
1) Атрибуты класса  
2) Атрибуты свойств  
Порядок соответствует Reflection‑порядку объявления.

## AttributeContext
Контекст, который получает обработчик:
- `attribute` — инстанс атрибута
- `target` — ReflectionClass или ReflectionProperty
- `type` — Request/Dto
- `stage` — PipelineStage
- `context` — PipelineContext
- `data` — текущие данные (если обработчик работает со стадией)

## StageProcessor
Pipeline вызывает `AttributeRegistry::processStage()` для каждой стадии.
Это позволяет кастомным атрибутам модифицировать данные на нужном этапе.

## Ограничения
- Атрибуты не должны выполнять бизнес‑логику.
- Тяжёлые вычисления выносить в сервисы/хуки.

## Где детали
- Полный перечень: `docs/guides/attributes/README.md`
- Примеры в контексте: `docs/guides/requests.md`, `docs/guides/dto.md`
