# Приёмка реализации

Дата: 2026-09-13. [План](pln-026-readme.md),
[исходный аудит](../../audit/aud-005-transport-response-retry/aud-005-readme.md).
Все В1–В5 согласованы. Коммит подготовки — `03673fd`.

## Результат

Реализованы все четыре этапа: классификация/диагностика транспорта и Retry-After,
внешний ExecutionDeadline, Auto/RawResponse, опциональная условная safety запроса.
ClientConfig, обязательные интерфейсы и Composer-зависимости не расширены.
Обновлены публичные справочники, миграция и CHANEGLOG.md без ссылок на workflow.

Runtime-поля копируются в RequestOptions; снятие внешнего срока и nullable raw
override не теряется. RequestSpec получает необязательное поле marker, без нарушения
существующего конструктора. Ошибки safety не могут запустить повтор самой проверки.
Накопленная неопределённость отправки хранится только в PipelineContext текущего
запроса. ErrorCode не расширен; свойства transport exceptions добавлены совместимо.

## Проверки

| Проверка | Результат / доказательство |
| --- | --- |
| PHP 8.5, `composer test` | [1647 passed, 6033 assertions, 17 skipped](artifacts/php85.log) |
| PHP 8.4, `php vendor/bin/pest --compact` | [1647 passed, 6033 assertions, 17 skipped](artifacts/php84.log) |
| `composer test:redis`, изолированный Docker Compose | [17 passed, 92 assertions](artifacts/redis.log); стенд удалён runner-ом |
| `composer analyse` | [Нет ошибок](artifacts/analyse.log) |
| `composer lint` | [0 ошибок, 139 предупреждений длины строк](artifacts/lint.log); предупреждения неблокирующие по правилам репозитория |
| `composer check-docs` | [89 документов, локальные ссылки корректны](artifacts/docs.log) |
| `composer check-package -- --staged --report …` | [Архивы совпадают, standalone без dev-зависимостей прошли](artifacts/distribution.log), [состав и результаты](artifacts/distribution.json) |
| Контроль версии проверенных файлов | [SHA-256 и версии PHP](artifacts/source-manifest.json) |

В сохранённых логах удалены ANSI-последовательности и конечные пробелы;
содержимое результатов не изменено.

Относительно исходного набора добавлено 76 тестовых случаев и 250 assertions.
17 пропусков обычного набора — Redis, проверенный отдельной командой.
Дистрибутив проверялся по Git index с реализацией, а не по старому HEAD.
Standalone execution contracts дополнительно проверяет совместную работу deadline,
raw, conditional retry, HTTP-date и PSR network normalization без Guzzle Client/Laravel.

## Соответствие матрице

- T1–T4: `TransportFailureContractTest`, `ExternalDeadlineTest` и существующие
  `HttpTransportTest`/`CustomHandlerTimeoutTest`: 55/56, previous, повторная
  нормализация, safety GET/POST, неизвестный исход и накопление по попыткам;
  локальный deadline и поздний HTTP/hook. Custom handler помечается консервативно
  перед передачей управления; встроенный handler — перед фактическим transport send.
- H1–H5: `RawResponseTest`: MIME/JSON/null/204/ошибки HTTP, marker и override,
  конфликт с DTO/пагинацией/download до HTTP, hooks/extension, один кеш для Auto/Raw.
  CacheManager/custom key не менялись; существующие проверки кеша проходят.
- S1–S4: `ConditionalRetrySafetyTest` и существующие retry/stream suites:
  POST только 429 при сохранённом network retry GET, приоритет safe/null,
  отказ до auth refresh, отсутствие устаревшего response, сторонний RequestInterface,
  отказ replay тела, отключённый retry, безопасное сообщение при исключении политики.
- B1–B7: `ExternalDeadlineTest` и бизнес-повтор в `ConditionalRetrySafetyTest`:
  1000/600/200 для последовательных вызовов, 1000/400 для внутреннего retry,
  минимум parent/client/external, часы, сброс/копирование, sync/async,
  auth refresh, отсутствие I/O после истечения и sleep при нехватке остатка.
- R1–R2: `RetryAfterContractTest` плюс существующие Retry-After/Timing suites:
  три формы даты, секунды, ноль, invalid/negative/overflow, единицы, единые часы,
  отказ до sleep и прежние статусы 429/503. Локальные quota exceptions не изменены.
- Z1: обе версии PHP, package standalone без dev-зависимостей, отсутствие изменений
  ClientConfig/Composer и старых обязательных интерфейсов.

## Границы

Это кооперативный deadline: произвольный блокирующий PHP-код нельзя принудительно
остановить; превышение обнаруживается при возврате управления. Одна системная
монотонная шкала применима только в текущем процессе, для custom clock требуется
один объект. Без внешней опции send независимы. NotSent требует доказательства,
unknown не сообщает, выполнил ли сервер бизнес-операцию. Диагностика не разрешает
POST retry. Семантическая совместимость меняется в описанных в миграции случаях.

Локально проверены locked-зависимости PHP 8.4/8.5 и штатный Redis-стенд. Весь набор
вариантов lowest/latest и матрица Redis в GitHub Actions остаются проверками CI;
локальные результаты не объявляются результатами ещё не запущенного CI.
