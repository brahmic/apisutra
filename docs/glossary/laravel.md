# Laravel интеграция

## RequestFactoryInterface
Namespace: `Brahmic\ApiSutra\Contracts\Factory\RequestFactoryInterface`.
Интерфейс фабрики для создания Request с заполнением из источника данных. Поддерживает `Request|array` (Laravel/CLI). В array‑режиме возможен структурированный формат: `route/query/body/headers/files`. Если передан простой массив без структуры — он используется одновременно как `query` и как `body`, а `route/headers/files` считаются пустыми.

## SdkServiceProvider
Namespace: `Brahmic\ApiSutra\Laravel\SdkServiceProvider`.
ServiceProvider пакета. Регистрирует фабрику запросов, реестр и резолвер клиентов, сервисы auto‑discovery. Также добавляет DI‑hook: при разрешении AbstractRequest заполняет его данными из RequestFactory и автоматически подставляет клиента через ClientResolver.
Если TransportInterface не задан в контейнере, пытается создать HttpTransport на базе PSR‑18 клиента.

## ContainerProviderInterface
Namespace: `Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface`.
Контракт провайдера контейнера. Позволяет изолировать core от прямого доступа к Laravel контейнеру и задать интеграцию через единый адаптер. Используется в AbstractRequest (резолв ClientResolver), ClientDiscoveryService (basePath), ClientConfig (debug/environment) и Validator (validatorFactory).

## ContainerProviderRegistry
Namespace: `Brahmic\ApiSutra\Support\ContainerProviderRegistry`.
Глобальный реестр провайдера контейнера. Поддерживает override и auto‑detect. В Laravel из коробки возвращает LaravelContainerProvider.

## LaravelContainerProvider
Namespace: `Brahmic\ApiSutra\Laravel\LaravelContainerProvider`.
Адаптер Laravel контейнера. Прокидывает bound/make/basePath/environment/debug и validatorFactory в термины провайдера.

## NullContainerProvider
Namespace: `Brahmic\ApiSutra\Support\NullContainerProvider`.
Null‑Object для случая без контейнера. Все методы возвращают `null`/`false`, чтобы core работал без Laravel.

## ClientResponseAdapterInterface
Namespace: `Brahmic\ApiSutra\Contracts\Response\ClientResponseAdapterInterface`.
Контракт адаптера, который преобразует ClientResponse в Laravel Response.

## ClientResponseAdapter
Namespace: `Brahmic\ApiSutra\Laravel\ClientResponseAdapter`.
Дефолтная реализация адаптера. Возвращает JsonResponse для массивов/объектов, Response для строк и StreamedResponse для FileResponse.
