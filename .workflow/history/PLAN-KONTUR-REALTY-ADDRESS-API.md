# План: интеграция Address API в kontur-apisutra

## Цель
Добавить в SDK отдельный сервис `Address API` как часть `Kontur Realty`, с 1:1 endpoint-методами и типизированными DTO, без бизнес-оркестрации.

## Входные материалы
- OpenAPI: `packages/bezopasnosdelka/kontur-apisutra/raw/realty.address-api-api.json`
- Методология: `packages/brahmic/apisutra/docs/guides/provider-methodology.md`

## Зафиксированные решения
- `Address API` оформляется как отдельный ресурс-владелец в `Services/Realty/Resources/Address`.
- Точка входа из клиента: `RealtyClient::addresses(): AddressResource`.
- Конфиг/аутентификация остаются централизованными через текущий `RealtyClientConfigFactory`.
- Публичный DX: 1:1 методы + тонкие алиасы по именам use-case (без скрытой логики).

## Этап 1. Каркас ресурса
- Добавить `AddressResource` и метод `addresses()` в `RealtyClient`.
- Сгруппировать запросы/DTO/enum по подпапкам ресурса.
- Критерий готовности: ресурс виден в клиенте и компилируется.

## Этап 2. Endpoint 1:1 (requests)
- Реализовать request-классы:
  - `GET /realty/address/v2/objects/{cadastralNumber}`
  - `GET /realty/address/v2/address/{cadastralNumber}`
  - `GET /realty/address/v2/address?address=...`
- Добавить методы ресурса:
  - `getObjectInfoByCadastralNumber(...)`
  - `resolveAddressByCadastral(...)`
  - `resolveNotStructuralAddress(...)`
- Критерий готовности: все 3 endpoint доступны из ресурса.

## Этап 3. DTO и enum
- Смоделировать response DTO из схем:
  - `EstateObjectInfoDto`
  - `EstateObjectDto`
  - `StructuredAddressDto`
  - `AddressItemDto`, `HouseItemDto`, `RoomItemDto`
  - `ErrorDto` (для типизации контекста ошибки, где применимо)
- Использовать `#[Nested]` для вложенных структур и массивов.
- Для enum со строковыми кодами добавить `title()` (если enum вводятся).
- Критерий готовности: response-модели полностью гидрируются без raw-массивов в ключевых местах.

## Этап 4. DX-слой
- Сохранить 1:1 методы как базовые.
- Добавить тонкие алиасы:
  - `objectByCadastralNumber(...)`
  - `addressByCadastralNumber(...)`
  - `searchByAddress(...)`
- Критерий готовности: сценарии «поиск по адресу» и «резолв по КН» читаются в 1 строку.

## Этап 5. Ошибки и консистентность
- Проверить, что ошибки Address API проходят через глобальный `errorMapper`.
- Для нестандартных полей ошибки — добавить локальный DTO-мэппинг (без изменения transport flow).
- Критерий готовности: одинаковый формат ошибок в `ResultHandle` по всем realty-сервисам.

## Этап 6. Качество
- Линтер по измененным файлам.
- Финальный аудит API surface ресурса `addresses()`.
- Критерий готовности: чистая проверка и ровный DX.

## Целевой DX
```php
$object = $client->addresses()
    ->getObjectInfoByCadastralNumber('77:01:0001092:11')
    ->dataOrFail();

$structured = $client->addresses()
    ->resolveAddressByCadastral('77:01:0001092:11')
    ->dataOrFail();

$matches = $client->addresses()
    ->resolveNotStructuralAddress('Москва, Тверская, 1')
    ->dataOrFail();
```
