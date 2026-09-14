# Приёмка исправлений гидратации

- Дата создания: 2026-09-14
- Дата обновления: 2026-09-14

Основание — [pln-029](pln-029-readme.md). Аудиты и исходный план зафиксированы
коммитом `b3fb3e7`. Проверки ниже выполнены на реализации поверх этого коммита,
PHP 8.4.15, с зависимостями из существующего `composer.lock`.
Изменённый код и документация перечислены в [SHA-256 manifest](artifacts/sha256.txt).

## Принятый результат

| Находка | Реализация и проверка |
| --- | --- |
| F8 | Выбор одиночного объекта по объявлению свойства; обычная constructor-first гидратация без `wrapCollection`. [NestedObjectHydrationTest](../../../tests/Unit/Serialization/NestedObjectHydrationTest.php): plain readonly/public/promotion, native/explicit/interface/union, PHP-объект, source/fallback, ошибки формы и дочерних полей, пользовательские коллекции, JSON/Returns sync и promise. |
| F5 | Provider охвачен дополнением текущего поля в структурированной HydrationException. [DefaultValueProviderContractTest](../../../tests/Unit/Serialization/DefaultValueProviderContractTest.php): Missing/Null/Present, дочерний объект, список, unwrap, суффикс, reason/expected/actual/previous, HTTP 200 и одна попытка. |
| F7 | Реестры constructor/client/global/extension не активированы для гидратации. [HydrationCastSourcesTest](../../../tests/Unit/Serialization/HydrationCastSourcesTest.php) использует plain DTO без профиля, отдельно проверяет сериализацию запроса, профиль и свойство. Прежний тест Hydrator переименован и проверяет профиль с пустым constructor registry. Публичный аргумент сохранён. |
| F6 | Основные тесты и исполняемый пример закрепляют Present-provider перед Nested, один Null/Present provider, missing/default, list/associative/sparse, known/unknown, индексы, Keep/нормализацию/Cast. Порядок обработки не изменён. |

Полный контракт и миграционные оговорки находятся в публичной документации:
[Nested и DefaultValue](../../../docs/guides/attributes/data-transfer.md),
[источники casts](../../../docs/guides/casts.md),
[changelog](../../../CHANEGLOG.md). Неоднозначные объявления теперь получают
ConfigurationException. Пустые JSON `{}`/`[]` после assoc decode не различаются.

Новые external rules, sourcePath, extras и строгие scalar-списки не реализованы:
они остаются в [pln-028](../../current/pln-028-declarative-dto/pln-028-readme.md).
Перестройка документации pln-027 не входила в работу.

## Проверки

Все команды выполняются из корня пакета; путь артефактов ниже уже соответствует
завершённому плану. Установка `composer install --no-interaction` использовала
существующий lock; версии зависимостей не менялись.

| Команда | Результат | Артефакт |
| --- | --- | --- |
| `vendor/bin/pest tests/Unit/Serialization --compact` | 424 passed, 1364 assertions | [serialization-tests.log](artifacts/serialization-tests.log) |
| `composer test` | 1706 passed, 6214 assertions; 17 optional Redis skipped | [tests.log](artifacts/tests.log) |
| `composer lint` | Exit 0, 0 errors; 138 предупреждений длины строк в существующем коде | [lint.log](artifacts/lint.log) |
| `vendor/bin/phpcs` для новых фикстур, трёх тестов и standalone helper | Exit 0, без замечаний | [tests-lint.log](artifacts/tests-lint.log) |
| `composer analyse` | Exit 0, без ошибок, baseline не расширялся | [analyse.log](artifacts/analyse.log) |
| `composer check-docs` | Все 89 публичных документов | [docs.log](artifacts/docs.log) |
| `php tests/Support/hydration-standalone.php` | Блокировка автозагрузки Illuminate, список загруженных классов Illuminate пуст; registry и пути проверены | [standalone.json](artifacts/standalone.json) |
| `php .workflow/completed/pln-029-hydration-defects/artifacts/check-examples.php` | Оба PHP-примера из документации выполнены дословно | [examples.log](artifacts/examples.log) |

В логах удалены только ANSI-оформление, пробелы на концах строк и пустые строки в конце файла.

Добавлено 59 сценариев. Начальный этап регрессий сохранён в
[before-fix-tests.log](artifacts/before-fix-tests.log): до изменения src первый
набор из 44 сценариев дал 29 failed и 15 passed. Затем набор расширен; этот лог
описывает начальную редакцию тестов, а не окончательный набор из 59 сценариев.

## Сопоставление с воспроизведениями коллеги

Команда `php .workflow/audit/aud-006-declarative-dto/artifacts/peer-review-probe.php`
выполнена без изменения самого probe и его ожидаемых результатов.
Новый вывод сохранён в [peer-review-after.json](artifacts/peer-review-after.json).
Exit 1 здесь ожидаем: probe фиксирует дефекты исходной версии.

Из 35 наблюдений изменились ровно 8:

| Сценарии | Результат после исправления |
| --- | --- |
| C01, C03 | Одиночный объект успешно гидратируется; HTTP 200 сохранён. |
| C04, C05 | Голый TypeError устранён. В этой конкретной фикстуре все поля дочернего DTO optional: неизвестный `wrapped` игнорируется и создаётся DTO с null/default. Это прежняя политика неизвестных полей, а не строгая проверка формы. Ошибочные обязательные поля отдельно проверены основными тестами. |
| C15, C16 | Пути `guardedChild.count` и `data.guardedChild.count`. |
| C23, C26 | Пути ошибок provider дополнены до `guardedIds`. |

Остальные 27 результатов совпали, включая scalar-списки, ограничения itemCast,
nullable/default, нормализацию, registry и response handler.
Исходные artifacts aud-006 и их контрольные суммы не переписывались.
