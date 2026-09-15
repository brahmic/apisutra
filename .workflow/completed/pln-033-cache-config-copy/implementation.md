# Реализация 033

База: `3ff9531` (032 завершён), PHP 8.4.15. Commit реализации определяется через
`git log --all --grep='feat(cache): реализовать план 033'`.

## Изменение и границы

ClientConfig имеет два независимых readonly-поля: cacheStore и cacheConfig.
Позиция cacheStore в конструкторе совпадает с прежним cache; CacheConfig больше
не принимает store. `with()` копирует оба поля без преобразования, явный null
меняет только названное поле. Другие параметры и их порядок не перестраивались.

CacheManager разрешает параметры отдельно и передаёт store в снимок выполнения;
clearScope/clearCache также используют только cacheStore. AuthBindingResolver,
AuthHandler и подключение auth в AbstractClient читают тот же единственный backend.
Алгоритмы identity, поколения, ключи, формат ответов/токенов и выбор auth locks
не менялись. Вводить новый resolver или контейнер не потребовалось.

Миграция охватила тестовые клиенты, standalone helpers, client showcase, публичные
справочники, реестр API и changelog. TestClientFactory различает отсутствие и null.
Объекты отдельных auth-расширений, RateLimitConfig.store, metadata cache и исходящие
кастеры не переименовывались. Старый API явно остаётся в отрицательных тестах,
разделе «до» миграции и неизменённых исторических артефактах.

## Матрица приёмки

| Строки | Проверки |
| --- | --- |
| C01–C03 | ClientConfigCacheTest: четыре формы подключения → with(), with(timeout), повторная копия |
| C04–C10 | ClientConfigCacheTest: полный блок, независимые перестановки store/params, оба null, сохранение ссылок; CacheStoreSeparationTest: Disabled → defaults, явный Enabled не восстанавливает backend |
| C11–C12 | ClientConfigCacheTest: удалённые аргументы, unpacking/fromLaravel, TypeError; независимые auth/retry/rules/массивы и прежний ClientConfigTest |
| C13–C15 | CacheStoreSeparationTest: чистые GET, cache hit копии, управляемый TTL старых/новых записей, разные store/prefix/identity |
| C16–C18 | CacheStoreSeparationTest: TokenAuthenticator с параметрами и без, timeout/ReadOnly/Disabled/withoutCache, отключение store с сохранением явных locks; перенесённые TokenIsolationTest/AuthLeaseContractTest: отсутствующая identity, store-provider и локальные locks |
| C19–C20 | ClockCache и ClientConfigCacheTest: отсутствие вызовов backend/identity/locks при конфигурировании, отсутствие очистки и клонирования; CacheStoreSeparationTest: долгоживущий исходный клиент и отключённая auth-копия; прежние auth recovery/lease тесты для ошибочного исполнения |
| C21 | CacheStoreSeparationTest: clearScope/clearCache, null store, параметры без store; прежние CacheManagerOverridesTest/CacheIsolationTest: атрибуты, режимы и поколения |
| C22 | CacheStoreSeparationTest читает сохранённый снимок старой версии; первый GET без HTTP, следующий запрос с прежним токеном без refresh |
| C23–C25 | ClientConfigCacheTest: явный null в factory, Reflection/property/позиции, fromLaravel/unpacking; реестр docs-api, client showcase и standalone smokes |
| C26 | Полный composer test и два dist-архива, включая hydration 032, auth, rate limits, готовые URL, файлы и сериализацию |

## Baseline и probe

[Команды baseline](artifacts/implementation-baseline/commands.json) фиксируют HEAD,
src tree, PHP, SHA исходников и исходные наблюдения. До изменения API выполнен
[capture-cache-baseline.php](artifacts/capture-cache-baseline.php) на `3ff9531`.
Ему нужен добавленный тестовый ClockCache; его SHA также сохранён. Все данные
искусственные, unixTime фиксирован. [Полученный снимок](artifacts/implementation-baseline/cache.stdout)
перенесён побайтно в `tests/Fixtures/cache-before-store-separation.json`.
Проверка не вычисляет ожидаемые ключи новым алгоритмом.

[Адаптированный upstream-probe](artifacts/migrated-upstream-probe.php) использует
исходные fixtures/bootstrap из issue, но переносит cache/cacheConfig на новый API.
Оригинал не менялся. Изменения адаптации:

- Обращения cache и аргументы прямого store стали cacheStore; store вынесен из блоков.
- U06 заменяет только CacheConfig и сохраняет store; U09 заменяет оба аргумента,
  воспроизводя намерение замены прежнего цельного блока.
- U07 означает отключение backend через cacheStore:null; имя наблюдения сохранено
  историческим, исходная поддержка cache:null не обещается.
- U15–U19 и все их условия гидратации не изменены. Opt-in 032 туда не добавляется.

[Проверяющий скрипт](artifacts/verify-implementation.py) задаёт ожидаемые изменения
U06/U07/U13/U14 вручную, остальные сравнивает с baseline независимо от environment.
[Результат](artifacts/implementation/upstream-comparison.json): 19 наблюдений,
нет расхождений с целевыми ожиданиями, AS-5 без opt-in неизменен.
Оригинальный `--verify` не предназначен для новой семантики; запускайте скрипт
проверки с новым выходным каталогом.

## Проверки

[Команды](artifacts/implementation/commands.json): адресные **179 passed / 860 assertions**;
общий прогон **2139 passed / 7898 assertions, 17 skipped** по прежним условиям среды.
Добавлены 15 тестов конфигурации/исполнения (134 assertions); остальные — перенесённые
регрессии. PHPStan по затронутому ядру и используемому TestingClientTrait прошёл;
trait включён в анализ, чтобы вызовы rebuildPipeline учитывались.

`composer check-docs`: **143 документа / 1419 ссылок**, 19 тестов проверяющего инструмента.
`composer analyse-docs` прошёл. [Git и Composer dist](artifacts/implementation/package-final.json)
проверены через `composer check-package -- --staged`, все 19 standalone smoke
в каждом архиве прошли, включая constructorValue и обновлённый client showcase
с with(timeout: 7). Финальные docs/check-docs и analyse-docs также сохранены отдельно.

Целевой [PSR-12 нового кода и ядра](artifacts/implementation/style-target.json) прошёл,
[PHP-синтаксис](artifacts/implementation/php-l.json) всех перенесённых файлов корректен.
Широкий PHPCS по старым тестовым файлам не полностью чист: после исправления новых
отступов/строк остаются **117 прежних нарушений против 120 на базе**, новых групп
нарушений по файлам не добавлено. [Сравнение с базой](artifacts/implementation/style-comparison.json)
и [скрипт](artifacts/compare-style.py) сохранены. Первая широкая проверка
с 151 замечанием оставлена как промежуточный лог; её exit code не выдан за успех.
Несвязанный массовый reformat старых тестов в план не включался.

После изменения только форматирования повторно выполнены PHP-синтаксис и архивные
smoke; семантика тестов не менялась. Whitespace проверен перед commit.

## Документы и завершение

Текущая семантика опубликована в [контракте кеша](../../../docs/reference/execution/cache.md),
[клиентской конфигурации](../../../docs/reference/client/configuration.md) и
[миграции](../../../docs/migration/v0.4.0-alpha.1.md#разделение-store-и-параметров-кеша).
Обновлены auth tokens/locks, attributes, live testing, glossary, development,
client showcase, docs-api.json и CHANEGLOG. Планы 032 и 033 реализованы последовательно.
