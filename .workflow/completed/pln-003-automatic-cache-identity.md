# Автоматическая identity кеша и опциональная RedactionPolicy

- Дата создания: 2026-09-12
- Дата обновления: 2026-09-12
- Статус: завершён

## Цель и согласованный объём

По согласованию с пользователем убрать обязательный ручной prefix из
[первой поставки](../completed/pln-002-cache-auth-diagnostics.md). Сохранить
логический custom key внутри автоматически определённого контекста identity/tenant.
Уточнить автоматическое применение RedactionPolicy и случаи настройки.

## Решение и границы

- Опциональный чистый контракт identity для authenticators, конфигурации tenant
  провайдера и запросов с динамическим tenant. Встроенные authenticators поддерживают
  его без настройки пользователем. Неопределённая identity запрещает кеширование.
- Пространство включает класс SDK-клиента, baseUrl, выбранную auth identity,
  конфигурационную tenant identity и дополнительный prefix. Runtime scope заменяет
  только дополнительный prefix. Имена auth scopes не заменяют identity.
- Tenant конкретного запроса разделяет группы внутри пространства клиента.
  Очистка клиента охватывает известные ему auth identities и tenant-группы запросов
  при конфигурационном prefix; runtime-пространства очищаются через execution.
- Custom key не включает обычные URI/body/headers. Защитные признаки учитывают
  origin и известные credentials фактического запроса. Изменение auth-данных
  в hooks не должно приводить к чужому cache hit. Специфичные tenant-поля объявляет SDK.
- Очистка и получение identity не запускают refresh, authenticate или hooks.
  Сохраняются поколения инвалидации, TTL, режимы, opt-in операций и result-first API.
- Произвольные transport-side credentials и скрытый tenant ядро не распознаёт;
  provider SDK обязан объявить этот контекст. Общий HTTP cache и async вне объёма.

## Проверка готовности

Проверить zero-config кеш, общие/разные credentials, одинаковый prefix у разных
identity, custom key и tenant, auth scope/NoAuth, неизвестный authenticator,
изменения credentials в hooks, очистку и гонку с выполнением. Прогнать полный набор
тестов и standalone smoke. Обновить guides, changelog и ссылки; отдельно объяснить,
что redaction не нужно передавать для встроенных правил.

## Результат и ограничения

Реализован `CacheIdentityProviderInterface::getCacheIdentity(?PreparedRequest $request = null)`.
Без PreparedRequest метод предоставляет стабильный контекст для поколений очистки;
с PreparedRequest — фактический контекст после auth/hooks для custom key. Встроенные
authenticators используют `CacheCredentialIdentity` для объявленных credential-полей.
Обычные изменения URI/body/headers сохраняют объединение custom key; credentials,
origin и объявленный tenant разделяют ответы. Неизвестная identity пропускает кеш
с `cache_reason=unknown_identity`. Консервативный запрет любых изменений hook не
потребовался: итоговый контракт позволяет описывать значимые поля явно.

`CacheConfig::identity` и request-контракт дополняют выбранную auth identity.
Динамический AuthorizationScheme с params provider, пользовательским formatter или
Stringable params остаётся без HTTP-кеша. Произвольные поля tenant и transport-side
credentials обязан объявить SDK провайдера. Ротация Bearer-токена может дать miss.
Существующие auth token keys и auth-lock не перерабатывались.

Профильные guides и changelog обновлены. Документация RedactionPolicy явно указывает
автоматическое применение встроенных правил и необходимость параметра только для
дополнительных headers/fields/paths. Исходные материалы issue/001 не изменены.

## Проверки

- Cache: 67 тестов, 330 assertions, без падений.
- `composer dump-autoload --optimize --strict-psr`: успешно.
- `composer test -- --compact`: 776 тестов, 2049 assertions, без падений;
  38 тестов с прежними deprecation-предупреждениями.
- Синтаксис 20 изменённых/новых PHP-файлов проверен через `php -l`.
- Четыре PHP-блока cache/logging guides выполнены с локальными тестовыми зависимостями.
- Отдельная копия с `composer install --no-dev`: standalone smoke успешно проверяет
  кеш без prefix, runtime-разделение, очистку и redaction без Illuminate и Guzzle HTTP Client.
  Повторение: `php tests/Support/standalone-smoke.php /path/to/no-dev-checkout`.
- Относительные Markdown-ссылки и `git diff --check` проверены.

Проверки выполнялись на PHP 8.5.4. Версия и composer.lock не менялись;
релиз не публиковался.
