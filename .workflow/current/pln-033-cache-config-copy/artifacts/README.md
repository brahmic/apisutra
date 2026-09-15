# Доказательства готовности 032/033

Общий [скрипт повторной проверки](recheck-readiness.py) и
[сохранённый прогон 2026-09-15](readiness-2026-09-15/) относятся к фактическому
состоянию до реализации. Исходные issue/probe не изменяются.

В сохранённых CLI-логах убраны хвостовые пробелы строк оформления и пустые строки
в конце файла. Содержимое результатов, порядок непустых строк и exit code сохранены; JSON сравнивается
структурно до такого оформления. Это нормализованные логи, не побайтовый снимок терминала.

Команда сохранённого прогона из корня репозитория:

```bash
python3 .workflow/current/pln-033-cache-config-copy/artifacts/recheck-readiness.py --output .workflow/current/pln-033-cache-config-copy/artifacts/readiness-2026-09-15
```

Существующий каталог не перезаписывается. Для повторения используйте новый путь,
например `--output /tmp/apisutra-readiness-recheck`, и сравните отчёты. После
реализации 033 старый probe потребует отдельной адаптации по migration; эта команда
воспроизводит именно исходный API и не является приёмкой нового.

## Содержимое прогона

| Файл | Что подтверждает |
| --- | --- |
| report.json | Итог и фактический git HEAD; новые матрицы D/C не объявляются пройденными |
| commands.json | Точные argv каждой команды и exit code; рабочий каталог — корень пакета |
| upstream.stdout / upstream-comparison.json | Все 19 наблюдений и отдельное сравнение с исходным снимком |
| upstream-verify.stdout / upstream-verify.stderr | Реальный отказ оригинального --verify при другом Composer reference |
| legacy-typing.stdout | Семь случаев различия Hydrator/setValue/coerce/noTransform |
| tests.stdout / tests.stderr | Адресные тесты и assertions; вывод сохранён целиком |
| public-docs.stdout / public-docs.stderr | composer check-docs и его тесты |
| workflow-docs.json | Точный список рабочих документов, число ссылок/якорей и ошибки |
| migration-inventory.json | Файлы и строки-кандидаты переноса тестов/helpers, строка live-руководства |
| source-trees.stdout / source-diff.stdout | Деревья src на HEAD/reference и сравнение рабочего src |
| php-version.stdout / composer-version.stdout | Среда повторного запуска |
| inputs-sha256.json | SHA-256 исходников, документов, probe и скриптов проверки |
| SHA256SUMS | Целостность сохранённых отчётов и логов; проверить sha256sum -c из каталога прогона |

## Воспроизведение адресных проверок

Команда ранее упомянутых 66 тестов / 204 assertions:

```bash
vendor/bin/pest --colors=never tests/Unit/Core/ClientConfigTest.php tests/Unit/Serialization/HydrationCompatibilityTest.php tests/Unit/Serialization/ExternalHydrationRulesTest.php tests/Unit/Cache/CacheManagerOverridesTest.php
composer check-docs
git diff --check
```

Прежние цифры 12/130 и 14/152 относятся к предыдущим редакциям документов, логи
тех запусков не были сохранены. Новый прогон фиксирует актуальную редакцию и явный
список файлов; не приписывает свежие доказательства старому несохранённому запуску.
С добавлением приложений число документов/ссылок закономерно меняется.

## Сравнение исходного probe

Сравниваются отдельно:

1. `observations` — структура, значения и порядок всех записей должны совпасть.
2. Среда — PHP, prettyVersion и Composer reference фиксируются как метаданные.
3. Реальный код — git HEAD, src tree и SHA-256, независимо от InstalledVersions.

В оригинальном bootstrap --verify проверяет observations **и reference**.
Различия PHP/prettyVersion сами по себе в условие отказа не входят. Поэтому совпадение
19 наблюдений при другом reference совместимо с exit code 1 оригинального --verify;
это не нужно выдавать за проход всей исходной команды. Результат сравнения среды
приведён в upstream-comparison.json, stderr отказа сохранён.

Этап 2 заново фиксирует commit начала реализации и свои baseline-материалы, в том
числе записи для C22. Этот прогон закрывает доказательства готовности документов и
фактуру текущих дефектов, не заменяет приёмку реализации или материал C22.
