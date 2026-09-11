# Архитектура SDK

Техническая документация для реализации.

## Документы

| # | Документ | Описание |
|---|----------|----------|
| 00 | [Overview](./00-overview.md) | Слои, компоненты, диаграмма |
| 01 | [Contracts](./01-contracts.md) | Все интерфейсы SDK |
| 02 | [Core Classes](./02-core-classes.md) | AbstractClient, AbstractRequest, AbstractResource |
| 03 | [Pipeline](./03-pipeline.md) | Жизненный цикл запроса |
| 04 | [DTO System](./04-dto-system.md) | Hydrator, Casts, Nested |
| 05 | [Transport](./05-transport.md) | TransportInterface, Http/Mock |
| 06 | [Config](./06-config.md) | ClientConfig и вложенные VO |
| 07 | [Attributes](./07-attributes.md) | Все атрибуты и их обработка |
| 08 | [Extension Points](./08-extension-points.md) | Как расширять SDK |
| 09 | [Implementation Order](./09-implementation-order.md) | Порядок реализации |

## Быстрый старт

1. Читать [00-overview](./00-overview.md) — общая картина
2. Читать [09-implementation-order](./09-implementation-order.md) — план работы
3. Реализовывать по фазам
