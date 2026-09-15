# Архитектура ApiSutra

Карта компонентов для разработчика пакета: кто принимает решения, как связаны
потоки выполнения и где подключаются расширения. Публичные параметры, приоритеты
и ограничения описаны в [справочнике](../reference/README.md).

## Цели SDK

- декларативные запросы через атрибуты;
- единый пайплайн выполнения;
- расширяемость через публичные контракты;
- предсказуемые результаты и ошибки.

## Ключевые компоненты

| Компонент | Ответственность |
| --- | --- |
| [ClientConfig](../../src/Config/ClientConfig.php) | Настройки и зависимости конкретного клиента |
| [AbstractClient](../../src/Core/AbstractClient.php) | Собирает сервисы и пайплайн, отправляет запросы, создаёт представления результата |
| [AbstractRequest](../../src/Core/AbstractRequest.php) / [RequestExecution](../../src/Request/RequestExecution.php) | Декларация операции и её обёртка с runtime-опциями |
| [RequestSpecResolver](../../src/Request/RequestSpecResolver.php) | Читает атрибуты запроса и собирает RequestSpec |
| [RequestResolver](../../src/Request/RequestResolver.php) | Выбирает одиночное выполнение или обход страниц |
| [Pipeline](../../src/Pipeline/Pipeline.php) | Организует отдельное выполнение и доставку его результата |
| [Serializer](../../src/Serialization/Serializer.php) / [Hydrator](../../src/Serialization/Hydrator.php) | Преобразуют запрос в HTTP-представление и данные ответа в DTO |
| [HydrationRules](../../src/Serialization/Rules/HydrationRules.php) / [HydrationScope](../../src/Serialization/Rules/HydrationScope.php) | Описывают внешние правила DTO и сохраняют их при вложенной гидратации |
| [ExecutionResult](../../src/Result/ExecutionResult.php) / [ResultHandle](../../src/Result/ResultHandle.php) | Хранят итог исполнения и предоставляют способы его получения |

`AbstractClient` собирает гидратор, сериализатор, реестры и кеш метаданных.
Реестры хуков, атрибутов и расширений можно передать в конструктор готовыми.
Один набор `hydrationRules` передаётся в гидратор и сериализатор клиента;
тот же гидратор используется для финального результата continuation.
Изоляция значений в кеше описана в [устройстве атрибутов](attributes.md#резолв-и-кеш).

## Принципы и границы

- Транспорт подставляется через [TransportInterface](../reference/execution/transport.md).
- Контейнер опционален. Auto-resolve требует зарегистрированного резолвера,
  а встроенная валидация — фабрики, которую можно передать явно.
  См. [создание клиента](../reference/client/construction.md) и
  [валидацию](../reference/client/validation.md).
- Внутри пайплайна ошибки по умолчанию доставляются через результат;
  `throwOnErrors`, `dataOrFail()` и `await()` задают явные границы исключений.
  Подробнее — [доставка ошибок](error-handling.md).
- Конфиг и значения runtime-опций неизменяемы; рабочее состояние исполнения
  хранится в `PipelineContext`.
- Атрибуты описывают конфигурацию; особенности протокола внешнего API принадлежат SDK провайдера.

## Потоки выполнения

Обычная отправка идёт через `AbstractClient` к `RequestResolver`: одиночный запрос
попадает в `Pipeline`, а `Paginator` вызывает исполнителя для каждой страницы.
Batch и pool организуют несколько отправок через клиент. Внутри пайплайна
composite собирает результат дочерних запросов, а depends-on сначала выполняет
зависимости и затем основную операцию.

Continuation начинается при явном ожидании результата: отдельный сервис оценивает
готовность операции и при необходимости отправляет poll-запросы через клиент.
Это отдельный цикл протокола провайдера. Режим `sendAsync()` определяет интерфейс
возврата promise и сам по себе не гарантирует неблокирующий HTTP.

Выбор исполнителей, передача опций и дочернего контекста — в
[потоках выполнения](execution.md).

### Пайплайн (упрощённо)

Для обычного запроса: контекст и бюджет → валидация → подготовка HTTP-запроса →
авторизация и хуки → кеш либо транспорт с retry/rate-limit → обработка ответа →
гидрация → результат.

Попадание в HTTP-кеш пропускает транспорт, но сохраняет обработку ответа.
Ошибка валидации завершает запрос до отправки; composite имеет собственную ветку
сборки результата. Точный порядок, точки расширения и ранние выходы описаны в
[пайплайне](pipeline.md).

## Резолв клиента

`$client->send($request)` явно выбирает исполнителя. При `$request->send()`
[AbstractRequest](../../src/Core/AbstractRequest.php) использует уже привязанный клиент
или получает `ClientResolverInterface` через `ContainerProviderRegistry`.

[ClientResolver](../../src/Resolver/ClientResolver.php) разворачивает `RequestExecution`
до исходного запроса и обращается в [ClientRegistry](../../src/Resolver/ClientRegistry.php).
Реестр выбирает самый длинный совпадающий namespace и кеширует найденный клиент
для класса запроса; новая регистрация сбрасывает этот кеш.

Auto-discovery заранее наполняет реестр. Оно не заменяет поиск клиента при отправке.
Если резолвер отсутствует, нужны явная отправка через клиент или `setClient()`;
если резолвер есть, но соответствие не найдено, возникает `ConfigurationException`.
При `setClient()` доступный резолвер также проверяет принадлежность классу клиента.

`ClientResolver` выбирает клиента, `RequestResolver` — способ выполнения запроса,
`RequestSpecResolver` — его метаданные. Регистрация, discovery и настройка контейнера
описаны в [поиске клиента](../reference/client/discovery.md).
Поведение проверяют [ClientResolverTest](../../tests/Unit/Resolver/ClientResolverTest.php)
и [ContainerProviderRequestResolverTest](../../tests/Unit/Core/ContainerProviderRequestResolverTest.php).

## Точки расширения

| Задача | Механизм и подробности |
| --- | --- |
| Выполнить действие на границе отправки или гидратации | [Хуки](../reference/extensions/hooks.md), исполняемые HookRunner |
| Подключить несколько обработчиков одним модулем | [ExtensionInterface и реестры](../reference/extensions/extensions.md) |
| Обработать собственный формат ответа | [Response handler](../reference/extensions/extensions.md#response-handlers-и-приоритет), выбираемый по MIME |
| Преобразовать значение поля | [Casts](../reference/serialization/casts.md); для вложенной гидратации — [HydrationScope](../reference/dto/scope.md) |
| Описать DTO без атрибутов | [Внешние правила полей](../reference/dto/field-rules.md) |
| Добавить собственную декларацию | [AttributeRegistry и обработчики атрибутов](attributes.md#кастомные-атрибуты-attributeregistry) |
| Подставить HTTP-клиент или стратегию авторизации | [Транспорт](../reference/execution/transport.md) и [авторизация](../reference/auth/strategies.md) |

Места вызова обработчиков — в [пайплайне](pipeline.md#подключение-расширений).
Публичные возможности расширения доступны автору SDK; внутренние сервисы ниже
служат для изменения самого ядра.

## Внутренний слой сериализации/гидрации

- **PropertyTypeInspector** — чтение объявленных типов и проверка соответствия значений.
- **SerializationValueResolver** — общий resolver значений для `DtoSerializer` и `RequestPartsCollector`.
- **HydrationTypeSelector** — выбор ветки union/объявленного типа для гидрации.
- **BuiltinHydrationCaster** — выбор cast из атрибута/профиля и встроенных преобразований.
- **RuleSetCompiler** — проверка внешнего набора до обработки DTO; правила применяет гидратор.
  [Контракт](../reference/dto/field-rules.md).
- **SafeScalarHydrationCaster** — безопасное приведение scalar-значений по типу DTO.

Общие механизмы преобразования следует развивать в этом слое, сохраняя согласованность
`Hydrator`, `DtoSerializer` и `RequestPartsCollector`. Публичные правила разделены на
[гидратацию DTO](../reference/dto/README.md) и
[исходящую сериализацию](../reference/serialization/README.md).
