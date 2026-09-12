# ClientConfig

Полная спецификация настроек клиента. Каждый блок вынесен в отдельный файл.

## Карта разделов
- [Auth](./auth.md)
- Credentials enrichment (встроенный блок `credentialsConfig`, см. Auth и Serialization)
- [Cache](./cache.md)
- [Retry](./retry.md)
- [Rate Limit](./rate-limit.md)
- [Timeouts & Delay](./timeouts-delay.md)
- [Observability](./observability.md)
- [Pool](./pool.md)
- [Pagination](./pagination.md)
- [Serialization](./serialization.md) (enum, dateTime)
- [Responses & Errors](./responses-errors.md) (error mapping, continuation token, provider async-await defaults)
- [Extensions](./extensions.md)
- [Archive](./archive.md)
- [Container Provider](./container-provider.md)

## Общие принципы
- Клиент задаёт дефолты; атрибуты запроса и runtime‑опции могут их переопределять.
- Все блоки независимы: используйте только то, что нужно.
- Для provider‑wide кредов в `body/query/form` используйте:
  - `credentialsConfig` (встроенный `CredentialsEnricher`)
  - `requestEnrichers` (кастомные `RequestPartsEnricherInterface`)

Политика переноса credentials: [OriginPolicy](auth.md#originpolicy), необязательна.
