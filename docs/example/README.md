# Исполняемые примеры

Примеры входят в поставку пакета. Для запуска нужен Composer autoload; локальные
фикстуры позволяют обойтись без сети и credentials.

| Пример | Что показывает |
| --- | --- |
| [Records SDK](sdk/README.md) | Конфигурация, транспорт, ресурс, запрос, DTO и ошибка HTTP |
| [Возможности DTO](dto-showcase/README.md) | Атрибуты и правила одного товара, DX/wire, defaults и диагностика |
| [Ожидание операции](continuation/README.md) | Pending/Ready, token, строгий финал и кеш await |
| [Правила DTO](hydration-rules/README.md) | Mapping, nested/each, strict-список, extras и scoped cast |

[Quickstart](../guides/quickstart.md) запускает Records SDK. Laravel binding того же
SDK проверяется отдельно и описан в [интеграции](../guides/integration/laravel.md).
Все контракты находятся в [справочнике](../reference/README.md).

Фрагменты справочников объясняют отдельный API-вызов и могут требовать уже созданного
клиента или модели. Полный воспроизводимый код начинается с README соответствующего
примера; классы лежат в отдельных файлах.
