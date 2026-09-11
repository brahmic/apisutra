# Attributes

Полный перечень атрибутов SDK с параметрами и эффектом.

## Группы
- [HTTP](./http.md) — когда нужно задать метод и endpoint.
- [Request](./request.md) — когда нужно собрать query/body/path/header/file или описать request-level DX metadata через `OperationDescriptor`.
- [Behavior](./behavior.md) — когда требуется изменить cache/retry/timeout/rate‑limit.
- [Data Transfer](./data-transfer.md) — когда нужно замаппить и провалидировать DTO, задать `Map`/`From`/`To`, `DefaultValue` и использовать built-in auto-cast.
- [Hooks](./hooks.md) — когда нужно вмешаться в жизненный цикл запроса.
- [Response](./response.md) — когда нужно задать тип ответа или режим download.

## Правила
- Полный перечень атрибутов находится здесь.
- В остальных гидах — только короткие примеры + ссылка сюда.
- Механизм и обработка кастомных атрибутов: `docs/technical/attributes.md`
