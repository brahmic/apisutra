# План: привязка RequestSpecResolver к AttributeMetadataCache

## Цель
Синхронизировать кэширование `RequestSpec` с режимами окружения (Local/Testing/Production), используя тот же `AttributeMetadataCache`, что и в клиенте. Логику работы SDK не менять.

## Нюансы
- В `Local/Testing` кеш метаданных отключён — `RequestSpec` не должен кешироваться.
- В `Production` кеш включён — `RequestSpec` должен кешироваться стабильно.
- При смене клиента на запросе нужно сбрасывать `RequestSpec` (чтобы учесть другой режим кеша).

## Шаги
1. **AbstractClient**
   - Сохранить инстанс `AttributeMetadataCache` в свойстве клиента.
   - Добавить геттер `getAttributeMetadataCache(): AttributeMetadataCache`.

2. **AttributeMetadataCache**
   - Добавить `isEnabled(): bool`, чтобы корректно контролировать кеширование `RequestSpec`.

3. **RequestSpecResolver**
   - Принимать `AttributeMetadataCache` из клиента.
   - Если `isEnabled() === false` — не использовать статический кеш `RequestSpec` (пересоздавать).
   - Если `isEnabled() === true` — использовать статический кеш как сейчас.

4. **AbstractRequest**
   - В `setClient()` если клиент — `AbstractClient`, установить `RequestSpecResolver` с его `AttributeMetadataCache`.
   - Сбросить локальный `RequestSpec`, чтобы пересчитать в нужном режиме.

## Критерии приёмки
- В Local/Testing спецификация не кешируется.
- В Production спецификация кешируется.
- Бизнес‑логика без изменений.
