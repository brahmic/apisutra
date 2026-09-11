# Коллекции и batch

## Коллекции

### AbstractTypedCollection
Базовая typed‑коллекция для DTO/enum. Проверяет тип элементов (`itemClass()`), поддерживает
`all()/first()/get()/has()/hasAny()/only()/except()/count()/isEmpty()/map()/filter()/values()/contains()/firstWhere()/pluck()/keyBy()/sortBy()/sortByDesc()/unique()/mapToArray()/toArray()`. `pluck()` возвращает `RawCollection`. При нарушении guard
выбрасывает `ConfigurationException`.
`map()` и `filter()` сохраняют ключи, для переиндексации используйте `values()`.

### RawCollection
Коллекция без типовой валидации (escape‑hatch). Поддерживает те же базовые методы, но не делает guard.

### RequestCollection
Типизированная коллекция запросов. Используется в CompositeRequestInterface и DependsOnRequestInterface для определения списка запросов. Поддерживает классы, инстансы и callable, даёт доступ по классу и итерацию. Неподдерживаемые элементы приводят к `ConfigurationException` при выполнении.

### ResultCollection
Типизированная коллекция результатов. Доступ по классу запроса через get(class), по индексу — через all()[index]. Содержит методы для проверки ошибок и фильтрации.

### BatchMeta
Implements ResultMeta. Метаданные batch-результата. Содержит: total (количество запросов), successful (успешных), failed (неуспешных), partial (частично успешных).

## Batch

### BatchExecutor
Внутренний компонент для выполнения коллекции запросов. Поддерживает Sequential и Parallel режимы, применяет FailStrategy. Используется и для Composite (compile-time), и для Batch (runtime).
В batch-режиме принимает RequestInterface, callable или class-string (инстанс создаётся из parent‑контекста).

### BatchConfig
Readonly class конфигурации runtime batch. Параметры: mode (ExecutionMode), failStrategy (FailStrategy), concurrency (макс. параллельных запросов). Передаётся в метод batch().
