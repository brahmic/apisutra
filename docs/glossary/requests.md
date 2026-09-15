# Запросы

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="httpmethod"></a> HttpMethod | HTTP-метод операции: GET, POST, PUT, PATCH или DELETE. | [Контракт](../reference/request/declaration.md) |
| <a id="requestinterface"></a> RequestInterface | Публичный контракт SDK-запроса, реализованный AbstractRequest. | [Контракт](../reference/request/declaration.md) |
| <a id="abstractrequest"></a> AbstractRequest | Базовый класс для всех запросов. | [Контракт](../reference/request/declaration.md) |
| <a id="body-dto"></a> Body DTO | В body можно передавать DTO (input‑контракт). | [Контракт](../reference/request/declaration.md) |
| <a id="abstractresource"></a> AbstractResource | Базовый класс ресурса API. | [Контракт](../reference/client/resources.md) |
| <a id="асинхронность-sdk-sendasync"></a> Асинхронность SDK (sendAsync) | Promise API выполнения SDK; штатный pipeline и HttpTransport не гарантируют неблокирующий I/O. | [Контракт](../reference/execution/transport.md) |
| <a id="sendasync"></a> sendAsync() | Вызов send(SendMode::Async), возвращающий ResultHandle; не гарантирует fire-and-forget. | [Контракт](../reference/execution/transport.md) |
| <a id="sendmode"></a> SendMode | Выбор синхронной отправки или интерфейса с promise; сам по себе не гарантирует параллельный I/O. | [Контракт](../reference/execution/transport.md) |
| <a id="отложенная-готовность-результата-polling"></a> Отложенная готовность результата (polling) | Сценарий, где провайдер возвращает промежуточное состояние, а финальные данные становятся доступны позже. | [Контракт](../reference/execution/continuation-await.md) |
| <a id="promiseinterface"></a> PromiseInterface | Контракт Guzzle promise, используемый API результатов; сам тип не определяет конкурентность транспорта. | [Контракт](../reference/execution/transport.md) |
| <a id="compositerequestinterface"></a> CompositeRequestInterface | Интерфейс для композитных (виртуальных) запросов. | [Контракт](../reference/request/composition.md) |
| <a id="dependsonrequestinterface"></a> DependsOnRequestInterface | Интерфейс для запросов с зависимостями. | [Контракт](../reference/request/composition.md) |
| <a id="preparedrequest"></a> PreparedRequest | Подготовленный HTTP-запрос с URL, заголовками, одним источником тела и опциями отправки. | [Контракт](../reference/execution/transport.md) |
| <a id="resolveendpoint"></a> resolveEndpoint() | Метод AbstractRequest для динамического определения endpoint. | [Контракт](../reference/serialization/uri-query.md) |
| <a id="resolvebaseurl"></a> resolveBaseUrl() | Метод AbstractRequest для переопределения базового URL. | [Контракт](../reference/serialization/uri-query.md) |
| <a id="withbaseurl"></a> withBaseUrl() | Создаёт исполнение с переопределённым baseUrl; применяется контракт изоляции назначения. | [Контракт](../reference/serialization/uri-query.md) |

[Все термины](README.md).
