# Pipeline

## Pipeline и контекст

### PipelineContext
Контейнер контекста выполнения. Содержит request, config, traceId, role (readonly) и мутабельные данные pipeline: preparedRequest, response, dto. Хуки могут модифицировать preparedRequest. Доступен на всех этапах pipeline. Метод child() создаёт контекст для nested/dependency запросов.

### RequestRole
Namespace: `Brahmic\ApiSutra\Enums\Execution\RequestRole`.
Enum роли запроса в pipeline. Root — корневой запрос, вызванный пользователем. Nested — вложенный запрос внутри Composite. Dependency — запрос-зависимость внутри DependsOn.

### PipelineContextBuilder
Внутренний класс для создания PipelineContext. Используется Pipeline для инициализации контекста с начальными значениями (request, config, traceId, role). Недоступен для внешнего кода.

### Serializer
Сервис сериализации запроса в PreparedRequest. Использует AttributeRegistry, QueryArrayFormat и NamingStrategy, применяет атрибуты (Path/Query/Body/Header) и касты.

### Request Lifecycle
Последовательность хуков, через которую проходит каждый запрос: beforeSend, HTTP-вызов, afterResponse, beforeHydrate, hydrate, afterHydrate. Применяется ко всем запросам независимо от типа.

## Хуки и обработчики

### Hook (enum)
Enum типов хуков: BeforeSend, AfterResponse, BeforeHydrate, AfterHydrate. Используется для централизованной регистрации в HookRegistry.

### HookPriority (enum)
Enum приоритета выполнения hook. First — в начале уровня, Normal — по порядку регистрации (default), Last — в конце уровня.

### HookRegistry
Сервис для централизованной регистрации обработчиков хуков. Позволяет регистрировать глобально, для конкретных Request или для DTO. Использует resolver (callable) для DI. Поддерживает naming (защита от дубликатов) и priority.

### Lifecycle Hooks
Методы жизненного цикла запроса: beforeSend, afterResponse, beforeHydrate, afterHydrate. Все хуки принимают PipelineContext, который содержит request, response, dto и другие данные. Порядок: централизованные глобальные → централизованные по типу → атрибуты → методы класса. beforeHydrate получает текущие данные и должен вернуть массив; дефолтная реализация возвращает входные данные.

### HookInterface
Базовый интерфейс для всех хуков. Метод handle(PipelineContext $context): ?array. Возвращаемое значение игнорируется, кроме BeforeHydrate; в остальных стадиях возвращайте null.

### BeforeSendHookInterface
Интерфейс для классов-обработчиков хука beforeSend. Extends HookInterface. Вызывается перед отправкой запроса. Доступ к request через context->request, к preparedRequest через context->preparedRequest.

### AfterResponseHookInterface
Интерфейс для классов-обработчиков хука afterResponse. Extends HookInterface. Вызывается после получения ответа. Доступ к response через context->response.

### BeforeHydrateHookInterface
Интерфейс для модификации данных до гидрации. Метод handle возвращает array (модифицированные данные). Исходные данные берутся из context->response->json(), затем могут быть изменены цепочкой хуков и beforeHydrate метода запроса.

### AfterHydrateHookInterface
Интерфейс для обработки после создания DTO. Extends HookInterface. Доступ к DTO через context->dto.

## Логирование и Audit

### TraceId
Уникальный идентификатор (UUID), связывающий все события одной операции. Устанавливается на уровне запроса (withTraceId), runtime‑опциями или клиента (setTraceId). Приоритет: runtime → запрос → клиент → автогенерация. Используется для интеграции с distributed tracing.

### PipelineEvent
Value Object события audit log. Содержит stage (этап), timestamp, duration, requestClass, role и payload. Payload заполняется только в debug-режиме.

### PipelineStage
Namespace: `Brahmic\ApiSutra\Enums\Pipeline\PipelineStage`.
Enum этапов pipeline для audit log: Started, BeforeSend, HttpRequest, HttpResponse, BeforeHydrate, AfterHydrate, Completed, Failed.

## Debug

### DebugInfo
Value Object, агрегирующий debug-информацию. Содержит preparedRequest (что отправили), response (что получили), время выполнения и вложенные debug-данные. Заполняется только в debug-режиме.
