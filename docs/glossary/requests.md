# Запросы

## HttpMethod
Namespace: `Brahmic\ApiSutra\Enums\Http\HttpMethod`.
Enum HTTP‑методов: GET, POST, PUT, PATCH, DELETE. Используется в RequestInterface и PreparedRequest.

## RequestInterface
Базовый интерфейс запроса. Методы: getMethod(), getEndpoint(), getResponseType(). Реализуется AbstractRequest.

## AbstractRequest
Базовый класс для всех запросов. Декларативное описание через атрибуты. Методы выполнения: send(), sendAsync(), resolved(), resolvedAsync(), dataOrFail(). send()/sendAsync() возвращают ResultHandle. Поддерживает resolveClient/resolveEndpoint/resolveBaseUrl, runtime‑модификаторы (без кеша/retry/auth и т.п.), lifecycle hooks (before/after send/hydrate) и helper‑методы getMethod/getEndpoint/getResponseType.

## Body DTO
В body можно передавать DTO (input‑контракт). DTO сериализуется через `#[To]` и `Cast`, а транспортные параметры (query/path/header/file) остаются в Request. Для простых запросов можно оставлять примитивные `#[Body]` поля без DTO.

## AbstractResource
Базовый класс ресурса API. Группирует связанные endpoints и возвращает запросы/подресурсы. Используется как навигация: `$client->users()->get(1)`. Методы: `resource()` для вложенных ресурсов и `request()` для привязки клиента к запросу.

## Асинхронность SDK (sendAsync)
Термин для неблокирующего выполнения запроса на стороне SDK. Возвращается ResultHandle, а для промиса доступны rawAsync()/resolvedAsync(). Не меняет семантику ответа провайдера: это только про способ выполнения в SDK.

## sendAsync()
Метод AbstractRequest. Асинхронное выполнение запроса. Возвращает ResultHandle (alias send(mode: SendMode::Async)). Для fire-and-forget, параллельных запросов, интеграции с async runtime.

## SendMode
Namespace: `Brahmic\ApiSutra\Enums\Execution\SendMode`.
Enum режима отправки. Sync — обычный sync‑путь, Async — non‑blocking транспортный путь. Используется в send(mode: SendMode::Async).

## Отложенная готовность результата (polling)
Сценарий, где провайдер возвращает промежуточное состояние, а финальные данные
становятся доступны позже. `await()` использует poll-запрос и явный критерий
Pending/Ready/Failed; HTTP retry и `sendAsync()` решают другие задачи.
См. [Provider Async Await](../guides/provider-async-await.md).

## PromiseInterface
Интерфейс промиса (Guzzle Promises). Используется в ResultHandle::rawAsync() и resolvedAsync() для асинхронных операций.

## CompositeRequestInterface
Интерфейс для композитных (виртуальных) запросов. Не имеет собственного endpoint. Методы: requests() — возвращает коллекцию вложенных запросов, aggregate(ResultCollection, PipelineContext) — объединяет результаты в один DTO (опционален, дефолтная реализация в AbstractRequest).

## DependsOnRequestInterface
Интерфейс для запросов с зависимостями. Имеет собственный endpoint, но перед его вызовом выполняются запросы-зависимости. Определяет методы dependencies() для списка зависимостей и processDependencies(ResultCollection, PipelineContext) для обработки их результатов.

## PreparedRequest
Value Object, содержащий все данные для выполнения HTTP-запроса: метод, URL, query, заголовки, body, файлы и опции сериализации. Создаётся ядром на основе класса запроса. Используется и для выполнения, и для debug.

## resolveEndpoint()
Метод AbstractRequest для динамического определения endpoint. Приоритет: resolveEndpoint() → атрибут #[Get('/path')]. Используется когда путь зависит от логики (условия, версии API).

## resolveBaseUrl()
Метод AbstractRequest для переопределения базового URL. Приоритет: withBaseUrl() → resolveBaseUrl() → ClientConfig::baseUrl. Для запросов на другой домен (CDN, microservices).

## withBaseUrl()
Метод AbstractRequest. Runtime переопределение baseUrl для конкретного вызова. Возвращает clone запроса с изменённым baseUrl.
