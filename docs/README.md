# Документация ApiSutra

Готовые ссылки и изоляция credentials: [Внешние и подписанные URL](guides/external-urls.md).

ApiSutra — фреймворк для построения API‑клиентов на PHP. Он объединяет
декларативные запросы и DTO через атрибуты, унифицированный pipeline
выполнения и расширяемость через extensions. Пакет ориентирован на Laravel,
но не требует его для работы: контейнер опционален, транспорт можно подключить
в любом окружении.

## Ключевая идея
ApiSutra делает SDK‑уровень предсказуемым: вы описываете запросы, DTO и
поведение декларативно, а инфраструктура (auth, cache, retry, rate‑limit,
pagination) работает единообразно для всех клиентов. Это не HTTP‑клиент
«сам по себе», а каркас для SDK‑клиентов и провайдеров данных, который
минимизирует ручной glue‑код и поддерживает единый стиль интеграций.

## Что такое провайдер
В контексте ApiSutra «провайдер» — это конкретный SDK‑клиент для внешнего API.
Он включает набор запросов, DTO и настройки клиента, а также правила поведения
(auth, retry, rate‑limit, pagination) и соглашения интеграции. Провайдер
упаковывает все детали внешнего API в единый, типизированный интерфейс,
который можно переиспользовать в разных проектах.

## Для кого и зачем
Если вы создаёте SDK‑клиентов внешних API (провайдеров данных), ApiSutra помогает:
- быстро описывать запросы и ответы без ручной сериализации
- централизованно управлять auth, кешем, retry, rate‑limit и пагинацией
- получать единый формат ошибок и результатов
- наращивать функционал через расширения без модификации ядра
- держать консистентность между командами и сервисами

## Что есть «из коробки»
- атрибуты для HTTP, request/response и DTO‑маппинга
- каноническая DTO DX-сериализация через `toArray()` и отдельный safe wire contract для outbound body
- pipeline с хуками, retry, rate‑limit, кешированием и временем ожидания
- pre-serialize enrichment request parts (provider credentials в body/query/form)
- unified continuation token DX через pluggable extractor в `resolved()`/`ResultHandle`
- unified provider async-await DX: `ContinuationMode`, `ContinuationResult`, `await()/awaitByToken()`
- пагинация и коллекции результатов
- batch и pool для конкурентного выполнения
- мегаклиент для мультисервисных интеграций
- тестовые инструменты (fake, record/playback, fixtures)
- интеграция с Laravel и auto‑detect контейнера

## Границы и требования
- транспорт не навязывается: используется `TransportInterface`
- адаптер `HttpTransport` опирается на PSR‑18 и PSR‑17 при необходимости
- в Laravel транспорт может быть подставлен автоматически через контейнер
- контейнер опционален: без него недоступен auto‑resolve клиента и DTO‑валидация
- для внешнего кеша и общего счётчика лимитов можно передать PSR-16 store;
  строгая межпроцессная квота [не гарантируется](guides/client-config/rate-limit.md#ключ-и-store)
- multipart‑загрузка требует `guzzlehttp/psr7`
- PHP 8.4+, акцент на неизменяемость и строгую типизацию

## Как читать документацию
- Быстрый старт: [Guides](./guides/README.md) → Quickstart
- Каноническая DTO hydration/serialization модель: [Guides](./guides/dto.md), [Serialization](./guides/serialization.md) и [ClientConfig Serialization](./guides/client-config/serialization.md)
- Анализ провайдера: [Анализ провайдера](./guides/provider-analysis.md)
- Методология провайдера: [Методология провайдера](./guides/provider-methodology.md)
- Мультисервисная архитектура: [Мегаклиент](./guides/megaclient.md)
- Стратегия версий: [Версионирование сервисов](./guides/versioning.md)
- Архитектура и поток выполнения: [Technical](./technical/README.md)
- Термины, сущности и ссылки: [Glossary](./glossary/README.md)
- Прикладные сценарии: [Guides](./guides/README.md) → Use‑cases, Batch, Pagination
- Long-running continuation token: [Guides](./guides/continuation-token.md)
- Provider async-await: [Guides](./guides/provider-async-await.md)

## Основные разделы

- [Guides](./guides/README.md) — практические инструкции
- [Примеры](./example/) — примеры выполнения запросов и оформления документации
- [Technical](./technical/README.md) — обзор архитектуры и потоков
- [Glossary](./glossary/README.md) — базовые определения

## Правила структуры
- Полные перечни: `guides/attributes/*` и `guides/client-config/*`.
- Гайды содержат краткие примеры и ссылки на «базу».
- Технические разделы — обзорные, без длинных списков параметров.
