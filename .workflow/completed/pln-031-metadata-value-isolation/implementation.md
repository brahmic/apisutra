# Приёмка реализации 031

- Дата создания: 2026-09-14
- Дата обновления: 2026-09-14

Реализация — `0950b773573f22b21aa0c14a357dee288478b90c`, принятый baseline —
`104f98e1fbb322972603b26adf9f37396b087670`. Контракт — [contracts.md](contracts.md),
матрица — [acceptance.md](acceptance.md).

## Изменение

Hydrator хранит только наличие constructor default и опускает отсутствующий аргумент:
выражение вычисляет PHP при создании DTO. Метаданные трёх преобразователей разделены
на безопасные значения и ReflectionAttribute для повторного создания. Первый
созданный экземпляр используется сразу; в кеш с объектными аргументами он не попадает.
Объектные массивы проверяются рекурсивно, включая непубличные свойства атрибутов.
Публичные сигнатуры не изменены.

## Матрица

Основные регрессии — [MetadataValueIsolationTest](../../../tests/Unit/Serialization/MetadataValueIsolationTest.php):
27 тестов / 211 assertions. Полный пакет: 1733 passed / 6425 assertions,
17 Redis-сценариев пропущены стандартным запуском без Redis. Изменение Redis не затрагивает.

| Строки | Основание приёмки |
| --- | --- |
| M01–M03, M06, M09 | Независимые defaults и вложенные объекты в прямом hydrate, коллекции, DTO::from и DefaultValue; изменение первого не видно во втором |
| M04–M05, M07 | Счётчики new и исключения для missing/present/null на constructor-first и property-fill путях |
| M08 | Scalar/enum/array и variadic в новой регрессии; required и несовпадения типов также проверены существующим HydratorTest |
| M10–M14, M12a | Прямые hydrate/DtoSerializer, query/body, вложенный wire DTO, массивы объектных args; header/path остаются без Cast |
| M15 | Через публичный AttributeMetadataCache::get проверены идентичность безопасных metadata и пустые фабрики всех трёх преобразователей после повторных вызовов; ветка cache hit не создаёт безопасные атрибуты заново |
| M16 | Returns, две страницы с контейнером и withItems, два элемента страницы, CompositeFlow одного клиента; Production/Local/Testing |
| M17–M19 | Две операции клиента из singleton ClientRegistry после снятия внешней ссылки; отдельный клиент; реальный локальный Illuminate Container и SdkServiceProvider без сети |
| M20 | Рекурсивные DTO, чередование типов, прогрев, ошибки new в defaults и аргументах Cast с успешным следующим вызовом; WeakReference подтверждает освобождение default-объекта при живом кеше |
| M21 | Просмотрены RequestSpecResolver, RequestSpec, RequestDefaults, AttributeRegistry, warmup и оба profile resolver; действующие значения RequestSpec — scalar/enum и массив HTTP-кодов Retry.retryOn, RequestDefaults — enum; общий исправляемый объектный default в них не найден |
| M22 | Входной объект сохраняет идентичность; готовый cast из ClientConfig сохраняет счётчик между запросами; действующие профильные тесты проходят |

В M21 речь о поддерживаемых декларациях: произвольные объекты вместо HTTP-кодов
Retry.retryOn не являются допустимыми правилами retry. Profile resolver не кеширует
экземпляры деклараций, явно возвращённые профилем casts остаются общими по контракту.

## Стоимость

[Baseline](artifacts/implementation-baseline-cost.json) и
[реализация](artifacts/implementation-after-cost.json) измерены на одной машине,
PHP 8.4.15 CLI, OPcache CLI/JIT выключены, по семь прогонов каждого сценария.
Сохранены также [удвоенные итерации](artifacts/implementation-double-cost.json)
и [проверка P01–P04](artifacts/implementation-cost-checks.json).

- P01: все четыре сценария безопасных деклараций укладываются в выбранный порог.
- P02: прогретый кеш сохраняет преимущество над выключенным по каждому сценарию
  с учётом разброса и нормализации числа операций.
- P03: в первичном cold cast-замере при удвоении итераций удержание выросло
  с 216 до 65752 байт. Эти данные сохранены. Дополнительный
  [memory probe](artifacts/implementation-memory.php) после прогрева показывает
  нулевое удержание на 1200/2400/4800 операциях в пяти сценариях, включая cold cast:
  [результат](artifacts/implementation-memory.json). Пропорционального роста нет.
  216 байт исходного измерения включает добавление записи в массив времён benchmark.
- P04: время объектного пути сохранено; новые вычисления new — цена исправления.

Замеры after/double выполнены до коммита реализации, поэтому содержат HEAD baseline.
Измеренные изменения src вошли в `0950b77` без последующих изменений;
текущие SHA-256 сохранены в [implementation-state.json](artifacts/implementation-state.json).

## Проверки и воспроизведение

Исходный metadata-probe намеренно ожидает дефект и после исправления возвращает 1.
Его [новый вывод](artifacts/implementation-after-probe.json) проверен по отдельно
заданным ожидаемым observations, исходные checks и baseline не переписаны.
Регрессии и полный прогон сохранены в [отдельном логе](artifacts/implementation-regressions.log)
и [логе пакета](artifacts/implementation-tests.log).

```bash
python3 .workflow/completed/pln-031-metadata-value-isolation/artifacts/verify-implementation.py
python3 .workflow/completed/pln-031-metadata-value-isolation/artifacts/verify-implementation-cost.py --check-only
```

Без `--check-only` проверка стоимости заново снимает after/double. Перед сравнением
другой версии baseline следует измерять на той же машине по сохранённому коммиту.

`composer lint`: 0 ошибок, 135 предупреждений о длине строк; `composer analyse` —
без ошибок; `composer check-docs` — 89 документов; строгая PSR-автозагрузка прошла.
`composer check-package -- --staged`: Git/Composer dist совпадают, standalone smoke
без dev-зависимостей прошли. Обновлены DTO/casts/config и changelog.

CR-01 закрыт для заявленной области. Исправленный гидратор готов для передачи
в continuation по плану 030.
