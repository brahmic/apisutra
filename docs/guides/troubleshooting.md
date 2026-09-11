# Troubleshooting

Краткие решения частых проблем.

## Клиент не установлен для запроса
**Причина:** запрос отправляется без клиента и без `ClientResolver`.  
**Решение:** вызвать `$request->setClient($client)` или настроить резолвер через контейнер.

## Auth scope не найден
**Причина:** используется `#[AuthScope]`, но scope отсутствует в `ClientConfig.authScopes`.  
**Решение:** добавить scope в конфиг или изменить атрибут.

## Auth включен, но auth не настроен
**Причина:** вызван `withAuth()`/`forceAuth()` или задан `AuthPolicy`, но `auth` не задан.  
**Решение:** задать `ClientConfig.auth` или убрать принудительное включение.

## После обновления изменилось поведение union DateTime/string
**Симптом:** поле `string|DateTimeInterface` сериализуется иначе, чем раньше.  
**Причина:** выбор ветки union теперь делается по runtime‑значению, а не по первому типу в объявлении.
`DateTimeCast::serialize()` теперь принимает только `DateTimeInterface`, а DX и wire serialization могут использовать разные policy layers.
**Решение:** проверить тип поля, `DateTimeFrom` / `DateTimeTo`, `DtoSerializationProfile` и при необходимости `ClientConfig.requestDateTime` / `wireBodySerializationPolicy`.

## Запрос не поддерживает пагинацию
**Причина:** `paginate()` вызван на запросе без `PaginableInterface`.  
**Решение:** наследоваться от `AbstractPaginatedRequest`.

## DTO‑контейнер пагинации не реализует интерфейс
**Причина:** для paginated запроса задан `#[Returns]`, но DTO не реализует
`PaginationItemsContainerInterface`.  
**Решение:** реализовать интерфейс или убрать `#[Returns]` для этого запроса.

## Файлы не скачиваются
**Причина:** нет `#[Download]` или ответ не является файловым.  
**Решение:** добавить `#[Download]` и использовать `FileResponse`.

## MultipartStream недоступен
**Причина:** отсутствует `guzzlehttp/psr7` для multipart.  
**Решение:** установить `guzzlehttp/psr7`.

## Валидация DTO не срабатывает
**Причина:** нет доступного validator‑factory.  
**Решение:** настроить `ContainerProvider` или вызвать `Validator::useFactory()`.

## Незамоканный запрос в тестах
**Причина:** включён `preventStrayRequests()` и нет фикстуры/мока.  
**Решение:** добавить `fake()`/фикстуру или отключить защиту.

## Rate Limit не разделяется между процессами
**Причина:** нет общего PSR‑16 хранилища.  
**Решение:** задать `RateLimitConfig::store`.
