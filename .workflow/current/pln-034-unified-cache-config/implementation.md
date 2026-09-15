# Реализация и доказательства

Baseline принятого commit — [baseline.json](artifacts/baseline.json): 427672a.
Работа выполнена в отдельном worktree. На момент его создания параллельные правки
файловых примеров уже вошли в baseline; их код не переписывался.

## Изменения

Store перенесён в CacheConfig, добавлен with(), удалён cacheStore клиента.
CacheManager, auth bindings/handler и сборка клиента читают единственный блок;
атрибут Cache копирует его с сохранением зависимостей. Перенесены фабрики, тесты,
client showcase, справочник и API registry; добавлена версия миграции 0.5.

## Приёмка

| Критерии | Доказательство |
| --- | --- |
| U01–U06 | ClientConfigCacheTest: ссылки, полная/частичная замена, null, TypeError/Error, позиции, Laravel, отсутствие IO |
| U07–U12 | UnifiedCacheConfigTest: GET/TTL, отключение тремя способами, auth/locks, прежние записи и долгоживущий клиент; AutomaticCacheIdentityTest/AuthLeaseContractTest: tenant, атрибут и fallback locks |
| U13 | Документация и типизация примеров прошли; client showcase выполняется в обоих архивах |
| U14 | Тесты, analyse, lint и финальные архивы прошли; ожидается CI релизного commit |

## Проверки

- [Baseline](artifacts/baseline-tests.json): 179 тестов / 860 assertions.
- [Целевые проверки](artifacts/target-tests-final.json): 184 / 922.
- [Полный прогон](artifacts/tests.json): 2144 passed / 7960 assertions, 17 skipped
  (Redis-сценарии требуют отдельного окружения CI).
- [PHPStan](artifacts/analyse.json), [lint](artifacts/lint.json),
  [стиль новых проверок](artifacts/style-tests-final.json), [composer validate](artifacts/composer-validate.json): успешно.
  Lint ядра: 0 errors, 180 предупреждений длины строк по настройкам репозитория.
- [Документация](artifacts/docs.json): 146 документов / 1467 ссылок, 19 тестов checker;
  [типы примеров](artifacts/docs-types.json): успешно.
- [Первый package-прогон](artifacts/package.json): все 20 smoke прошли в каждом архиве;
  сравнение состава нашло только служебный .git-файл worktree, который Composer archive
  включил как обычный файл. [Финальная проверка](artifacts/package-final.json) из
  основного checkout прошла: состав Git/Composer совпадает, 724 файла и 20 standalone
  smoke в каждом архиве без dev-зависимостей. [Полный манифест](artifacts/package-final-report.json).

Первый целевой прогон выявил оставшуюся старую форму изменения только identity в
тесте: новый блок не содержал store. Фикстура переведена на CacheConfig::with(),
сохранена исходная проверка изоляции tenant. Ошибки стиля нового теста исправлены.
Полные выводы находятся рядом с JSON команд. run-check.py запускается из корня
репозитория: `python3 <путь>/artifacts/run-check.py <метка> <команда> [аргументы]`.
Из логов удалены ANSI цвета, пробелы в концах строк и пустые строки в конце файла;
коды возврата и содержательные результаты сохранены.
