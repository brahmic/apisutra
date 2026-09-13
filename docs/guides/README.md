# Guides

Изменения совместимости: [миграция на v0.2.0-alpha.1](migration.md).

Готовые ссылки и изоляция credentials: [Внешние и подписанные URL](external-urls.md).

Практические руководства по созданию SDK‑клиентов, запросов и DTO в ApiSutra.
Здесь — путь «как сделать»: шаги, рекомендации, минимальные примеры и
типовые сценарии внедрения. Полные перечни и параметры вынесены в отдельные
разделы (attributes, client‑config), чтобы гайды оставались компактными.

Эти материалы рассчитаны на разработчика провайдера: вы строите клиент,
описываете запросы и DTO, настраиваете поведение и тестируете интеграцию
на едином наборе правил и соглашений.

## Какой маршрут выбрать
- Нужно быстро запустить клиента: Быстрый старт → Запросы → DTO
- Проектируете провайдера: Анализ провайдера → Методология провайдера → ClientConfig → Attributes → Тестирование
- Если у провайдера несколько сервисов: Мегаклиент → Client discovery → Laravel
- Если важна стратегия версий: Версионирование сервисов → Мегаклиент → Методология провайдера
- Ищете точечную настройку: сразу переходите в нужный гайд или справочник
- Отлаживаете поведение: Troubleshooting → Glossary → Technical

## Как устроена детализация
- Гайды — практические шаги и примеры
- Attributes и ClientConfig — полные перечни и параметры
- Glossary — термины, сущности и ссылки на источники
- Technical — обзор архитектуры и потоков исполнения

## Важный принцип сериализации
- `toArray()` — каноническая DX-сериализация DTO
- `from()` — каноническая гидрация DTO
- outbound body по умолчанию подчиняется wire policy, а не обязан совпадать с `toArray()`
- standalone `from()` и pipeline hydration должны давать один и тот же результат
- hydration DTO semantics централизуются через `DtoHydrationProfile`
- DX DTO serialization semantics централизуются через `DtoSerializationProfile`
- request/query/header/path semantics централизуются через `ClientConfig`
- wire body semantics централизуются через `ClientConfig::wireBodySerializationPolicy`
- class-level и property-level override допустимы, но базовый путь должен оставаться централизованным

## Что вы получите
- базовый каркас клиента и типовой набор запросов
- стандартизированные DTO и валидацию
- канонический `toArray()` для DTO и безопасный wire body default
- контроль ошибок, ретраев и лимитов
- централизованный enrichment provider credentials без дублирования в request-классах
- единый DX получения continuation token для long-running операций
- единый async-await DX для optional async методов провайдера
- предсказуемую структуру SDK, удобную для поддержки

## Примеры кода
- В пределах одного файла `use`в примерах документации указывается только при первом упоминании класса.
- Полные неймспейсы ищите в глоссарии и справочниках по сущностям.
- Анонимные классы — только как альтернатива для прототипов, в примерах — именованные.

## Содержание
- [Быстрый старт](./quickstart.md)
- [Конфигурирование клиента (ClientConfig)](./client-config/README.md)
- [Аутентификация](./auth.md)
- [Анализ провайдера](./provider-analysis.md)
- [Методология провайдера](./provider-methodology.md)
- [Чеклист разработки провайдера](./provider-checklist.md) — дорожная карта с пояснениями и ссылками
- [Provider Catalogs](./provider-catalogs.md)
- [Operation Inventory](./operation-inventory.md)
- [Мегаклиент](./megaclient.md)
- [Версионирование сервисов](./versioning.md)
- [Запросы](./requests.md)
- [Сериализация запросов](./serialization.md)
- [DTO](./dto.md)
- [Коллекции](./collections.md)
- [Валидация](./validation.md)
- [Casts](./casts.md)
- [NamingStrategy](./naming-strategy.md)
- [Пагинация](./pagination.md)
- [Batch](./batch.md)
- [Retry и Rate Limit](./retries-rate-limit.md)
- [Файлы](./files.md)
- [Resources](./resources.md)
- [Client discovery](./client-discovery.md)
- [Request pipeline (Composite/DependsOn)](./request-pipeline.md)
- [Хуки](./hooks.md)
- [Логирование](./logging.md)
- [Extensions](./extensions.md)
- [Ошибки и результаты](./errors.md)
- [Continuation Token](./continuation-token.md)
- [Provider Async Await](./provider-async-await.md)
- [Laravel‑интеграция](./laravel.md)
- [Transport](./transport.md)
- [Use‑cases](./use-cases.md)
- [Тестирование](./testing.md) — core testing primitives пакета
- [Live-тестирование (real-request)](./live-testing.md) — рекомендуемые provider-side паттерны поверх core helper-классов
- [Troubleshooting](./troubleshooting.md)
- [Attributes](./attributes/README.md)
- [Glossary](../glossary/README.md)

- [Совместные квоты между workers: Redis](redis-rate-limit.md).
