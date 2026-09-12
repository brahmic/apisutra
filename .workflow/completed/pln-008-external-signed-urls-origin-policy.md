# Внешние и подписанные URL: сохранение адреса и изоляция credentials

- Дата создания: 2026-09-12
- Дата обновления: 2026-09-12
- Статус: завершён

## Цель и основание

Продолжить AS-06/AS-08 из [issue/001](../issue/001/technical-specification.md)
после [URI/query](../completed/pln-007-uri-query-contracts.md), коммит `b56adfa`.
Пользователь поручил подготовить план после объяснения сценария:
API возвращает готовую ссылку, SDK отправляет запрос по ней без изменения path/query.
D1–D3 согласованы пользователем 2026-09-12: «принято, приступай». Реализация разрешена.

Дать провайдеру возможность использовать полный URL для отдельного запроса,
сохраняя подписываемые байты и не передавая автоматически credentials исходного
SDK-клиента другому origin. Обычному пользователю не нужны обязательные политики,
списки доменов или флаги сохранения кодирования.

Пример целевого сценария: `POST /upload-sessions` возвращает URL, метод и необходимые
заголовки; SDK провайдера создаёт запрос загрузки с этими данными. Ядро не знает
названий upload endpoints и не вычисляет/проверяет подпись конкретного сервиса.

## Исходное состояние на b56adfa

- [RequestUrlBuilder](../../src/Serialization/RequestUrlBuilder.php) принимает только
  относительный endpoint, сохраняет исходные query-байты, исключает fragment.
  Снятие запрета абсолютного endpoint само по себе не решает передачу credentials.
- [Serializer](../../src/Serialization/Serializer.php) определяет base URL с учётом
  runtime override, затем собирает поля, применяет credentials/custom enrichers
  и continuation applicator. Следовательно, назначение запроса нужно определить
  раньше enrichment, а не только при окончательном соединении URL.
- [AuthHandler](../../src/Pipeline/Auth/AuthHandler.php) выбирает auth по runtime,
  атрибутам, scope и политике запроса; проверки origin пока нет. `forceAuth()`
  преодолевает `NoAuth`/`withoutAuth()`, но это не разрешение передачи на чужой адрес.
- [RequestFlowRunner](../../src/Pipeline/Flow/RequestFlowRunner.php) выполняет stages,
  auth и hooks до cache lookup. [RetrySender](../../src/Pipeline/Transport/RetrySender.php)
  повторно вызывает auth после 401. Окончательный адрес необходимо проверять
  до cache lookup и непосредственно перед каждой HTTP-попыткой.
- [RequestOptions](../../src/Request/RequestOptions.php) содержит `withBaseUrl()`,
  но отдельного override полного URL нет. Новый nullable override должен поддерживать
  явную очистку через `array_key_exists()`, без возврата к старому значению через `??`.
- В `ClientConfig` нет общего массива HTTP-заголовков. Источники — поля запроса,
  runtime headers, auth, enrichers/hooks и настройки HTTP-клиента.
- [GuzzleHttpClient](../../src/Transport/GuzzleHttpClient.php) принимает конфиг Guzzle;
  его `sendWithOptions()` отключает redirects, но не ограничивает default auth,
  cookies, headers/query клиента. В установленном `vendor/guzzlehttp/guzzle/src/Client.php`
  есть объединение request options с defaults. Нужна проверка фактической отправки,
  чтобы поздние defaults не вернули исключённые credentials.
- [TransportOptions](../../src/VO/Http/TransportOptions.php) описывает таймауты/deadline;
  нынешние интерфейсы транспорта не гарантируют сохранение request target и отсутствие
  скрытых credentials/redirects. Такую поддержку нельзя вывести из timeout capability.
- [RedactionPolicy](../../src/Diagnostics/RedactionPolicy.php) маскирует известные
  секретные имена; подпись может иметь произвольное имя и находиться в path.
  Для готовых ссылок одного расширения списка `signature/token` недостаточно.

Это анализ исходников, а не результат новых HTTP-тестов. База последней реализации:
1050 тестов без падений, 2996 assertions; 36 прежних deprecation.

## Объём

Включить полный URL, immutable runtime API, сохранение path/query, origin policy,
контроль автоматических credentials на SDK/transport границах, auth refresh,
проверки поздних изменений адреса, диагностику и документацию совместимости.

Сохранить обычную относительную сборку, формат boolean/query, таймауты, deadline,
safe retry и восстановление тела. Не включать новый async, потоковый download/sink,
переработку binary upload, автоматическое выполнение redirect или провайдерские подписи.
Остаток потоковой части AS-08 остаётся отдельной задачей.

## D1. Готовый URL без пересборки — согласовано и реализовано

### Вход и приоритет

Добавлен API: `$request->withUrl($url)` создаёт отдельный execution.
На исходной версии такого метода не было. `withoutUrl()` явно очищает override;
второй `withUrl()` заменяет первый, исходный request/config не меняется.

Приоритет: runtime полный URL → абсолютный `getEndpoint()` → текущая относительная
сборка с effective base URL. Абсолютный endpoint и `withUrl()` имеют одинаковый
контракт готового адреса. Не определять подписанность по названиям query-полей.

При полном URL base path/query, endpoint placeholders и `withBaseUrl()` не дописываются.
Сохранённый override base URL начинает действовать снова после `withoutUrl()`.
Относительный ввод в `withUrl()` — ошибка конфигурации; для него остаётся endpoint.

### Сохранение адреса

- Только абсолютные HTTP/HTTPS URL с host; userinfo, network-path `//host`, другие
  схемы, управляющие символы, пробелы, обратный slash и некорректные percent escapes
  отклоняются. Host для первой поставки — ASCII/punycode или корректный IPv6;
  не выполнять неявную IDNA-конверсию введённого адреса.
- Path и query сохранить байт-в-байт: `%2F`/`%2f`, `%20`/`+`, дубликаты, порядок,
  пустые значения и внутренние/граничные разделители. Не применять обычную
  нормализацию граничных `&` из относительного builder к готовому URL.
- Fragment исключить как неотправляемую часть. Для пустого path HTTP request target
  использует `/`; остальные исключения из сохранения адреса не вводить молча.
- Не подставлять `{name}` в готовый path. Невалидные литералы отклонять;
  уже закодированные символы не декодировать. Dot-segments не нормализовать:
  проверить фактический адаптер; если передать неизменно нельзя, отказ до HTTP.
- Любая непустая собранная query из полей, pagination, continuation либо явно
  включённого enricher — `configuration_error`, а не игнорирование или дописывание.
  Отсутствующие/пропущенные null и пустые списки не считаются дополнительными парами.
  Явно включённый null или пустая строка создают пару и потому конфликтуют.
- Query auth, включая явно включённый, не может менять готовый URL: отказ до HTTP.
  Для незаподписанных запросов с динамическими query остаётся относительная сборка.
  Дополнительный режим редактирования полного URL в эту поставку не вводить.
- Метод, body/stream и явно заданные заголовки берутся из запроса. Сохранение адреса
  не означает сохранение всей подписи при неверном методе, теле или signed headers;
  эти данные задаёт SDK провайдера по ответу upload-session.

По умолчанию для готового URL не применять клиентские auth, credentials enrichment
и общие request enrichers, даже при совпадении origin. Это предотвращает незаметное
изменение подписанного запроса. Явное включение проверяется по D2 и не отменяет
запрет изменения path/query. Автоматическую генерацию idempotency header проверять
по существующему контракту операции; не считать её доказательством безопасности upload.

Для этого нового режима рекомендовано обходить HTTP cache по умолчанию: ядро не знает
срока действия подписи. Явное включение cache для готового URL в первой поставке
отклонять, чтобы не выдавать локальный успех вместо проверки доступа сервером.
Существующий custom key для обычных запросов сохраняется внутри identity/tenant.

## D2. Изоляция credentials — согласовано и реализовано

Origin определяется как схема, host и effective port. Для сравнения нормализуются
регистр схемы/host и default port, но исходные path/query не меняются. Поддомены,
смена схемы и нестандартного порта — разные origin; DNS-совпадение не даёт доверия.
Эталон — исходный `ClientConfig.baseUrl`, не runtime `withBaseUrl()`.

| Сценарий | Автоматическое наследование credentials |
| --- | --- |
| Относительный endpoint на исходном origin | Существующее поведение |
| `withBaseUrl()` меняет только path/query того же origin | Существующее поведение |
| `withBaseUrl()` меняет origin | По умолчанию выключено |
| Полный URL, включая тот же origin | По умолчанию выключено по D1 |
| Явный auth на другом origin без отдельного разрешения | `configuration_error` до auth/refresh/HTTP |
| Разрешённый origin и явно выбранный auth | Разрешён выбранный механизм, кроме изменения готового path/query |

Отключение касается config auth/auth scopes и автоматических credentials в query/body/form.
Общие `requestEnrichers` на чужом origin также не запускать автоматически: SDK не может
знать, какие поля они заполняют. Не пытаться угадать секретность только по имени поля.
Явные поля запроса и runtime headers считаются данными для текущего назначения;
копирование токена вручную остаётся ответственностью вызывающего кода.

Реализованное исключение: необязательный `ClientConfig.originPolicy` с точным списком
доверенных origin, без wildcard и глобального `allowAll`. Дефолт доступен без создания
объекта пользователем. Наличие origin в списке разрешает явное включение credentials,
но само не включает auth/enrichment. Для готового URL действуют ограничения D1.
Не добавлять сразу дублирующие атрибут и runtime allowlist; при потребности в другом
доверии можно создать отдельную копию конфига/клиента.

`withAuth`, `withAuthScope`, `forceAuth`, `forceAuthScope`, runtime включение credentials
не отменяют origin policy. Сначала проверяется допустимость источника credentials,
затем действует существующий порядок auth внутри разрешённой области. `NoAuth` и
`withoutAuth()` сами по себе не являются выключателем всех query/body credentials.

При выключенной авторизации 401 не должен запускать refresh исходного аккаунта
или пустой auth retry. Для разрешённого auth сохранить scope, lock identity,
лимиты повторов и общий deadline. Каждый дочерний запрос вычисляет назначение заново;
разрешение одного внешнего запроса не распространяется автоматически на dependencies.

Совместимость: внешние `withBaseUrl()` могли неявно использовать токен клиента.
После изменения такие вызовы потребуют явного auth и разрешённого origin либо
отдельного клиента, настроенного на целевой сервис. Это осознанное изменение поведения,
его нужно согласовать и описать в migration note.

## D3. Граница гарантий и transport — согласовано и реализовано

Ввести один внутренний контракт назначения: исходный/целевой origin, режим URI,
разрешённые автоматические источники credentials, ожидаемый request target.
Вычислять до сбора частей; переносить через контекст и подготовленный запрос без
потери при `with()`, retry и пересчёте transport options.

Проверять после stages/auth/hooks до cache lookup и перед каждой HTTP-попыткой:

- Для готового URL любые изменения path/query/назначения — отказ.
- Для относительного запроса смена origin после начального выбора — отказ;
  не переносить уже добавленные credentials и не пытаться пересобрать тело задним числом.
  Правильный способ смены назначения — `withUrl()`/`withBaseUrl()` до pipeline.
- Поздний custom retry handler также должен проходить проверку фактически отправляемого
  запроса. Проверка до callback недостаточна, если callback способен его изменить.

Hooks и пользовательские реализации — доверенный PHP-код. Контроль назначения
защищает штатный путь SDK, но не является sandbox для кода, самостоятельно читающего
секреты или вызывающего сеть. Сохранить hooks, проверяя их результат; для повторного
подключения отключённых config enrichers нужен явный выбор, проходящий D2.

Штатный HTTP-адаптер для готового URL/чужого origin должен:

1. Не дописывать defaults auth/cookies/query/headers/body от исходного HTTP-клиента;
   учитывать также низкоуровневые cURL options, способные менять target или credentials.
   Сохранять необходимые сетевые настройки TLS/proxy/таймаутов, не смешивая proxy auth
   с credentials сервера назначения. При несовместимом override — явная ошибка.
2. Не следовать redirects. 3xx вернуть в текущий HTTP/result pipeline без второго
   запроса; наличие allowlist не разрешает автоматический переход. Не менять
   классификацию 3xx отдельным побочным эффектом этой задачи.
3. Сохранять request target на PSR-границе и фактически на локальном HTTP-сервере.
   Проверить Guzzle/cURL на dot-segments, encoded slash и пустом query delimiter.
4. Явно подтверждать поддержку этого контракта. Стороннему транспорту/PSR-клиенту,
   который её не заявляет, возвращать `configuration_error` до отправки защищаемого
   запроса. Timeout capability не считать подтверждением. Для обычных same-origin
   относительных вызовов дополнительное требование не вводить.

Предпочтение — небольшой отдельный capability-интерфейс и неизменяемые options,
без добавления обязательных методов в существующие публичные transport interfaces.
Точные имена согласовать с существующей структурой при реализации. Штатная сборка
выбирает поддержку автоматически; обычный пользователь не настраивает capability.

## Диагностика, ошибки и cache

- Формат URL, конфликт готового URL с query/auth, запрещённый перенос credentials,
  поздняя смена target и несовместимый транспорт — `configuration_error`, без retry.
  Сохранить result-first, raw/resolved/dataOrFail/throwOnErrors и promise-контракт.
- Ошибка не содержит исходной подписанной ссылки, значения токена или тела.
  Причина и безопасный origin достаточны для диагностики.
- Готовый URL считать чувствительным целиком: в безопасных логах/debug/fixtures
  сохранять origin, скрывать весь path/query. Не требовать от пользователя перечислять
  названия signature-параметров или секретных сегментов path. Применять правило до
  первого prepare-log; не менять отправляемый URL ради redaction.
- Проверить exported exception context и recorder. Доступ к исходному запросу
  через существующий явно небезопасный/raw API не выдавать за замаскированный экспорт.
- Cache policy и auth identity должны использовать действительный выбор auth после
  origin gate. Запрещённый запрос не может стать cache hit до проверки назначения.
  Сохранить изоляцию обычных запросов и согласованный custom key.

## Порядок реализации

1. Согласовать D1–D3 и границы совместимости. Перевести план в «в работе» только после
   поручения реализации. Не считать обсуждение подписи одобрением всех новых дефолтов.
2. Написать воспроизведение на текущей версии: отказ полного URL, перенос credentials
   через внешний `withBaseUrl()`, поздние defaults адаптера и смена target через hooks.
3. Реализовать выбор назначения и origin gate до enrichment; добавить immutable
   `withUrl()`/`withoutUrl()` и режим готового URI в builder.
4. Подключить gate к auth/cache/refresh и финальным проверкам перед send/retry;
   исключить изменения готового query через pagination, continuation и query auth.
5. Реализовать capability и штатный адаптер, проверить defaults/redirects/cURL.
6. Подключить безопасную диагностику полного URL и запрет cache этого режима.
7. Выполнить приёмку, обновить docs/changelog и записать результаты в план.

## Приёмка

| Проверка | Ожидание |
| --- | --- |
| Относительные endpoint и все тесты URI/query | Прежний контракт сохранён |
| Полный URL в endpoint и runtime override | Base URL не дописан; метод/body/явные headers сохранены |
| `%2F`, `%2f`, `+`, `%20`, дубли, `?`, граничные `&` | Сравнение точных request-target байтов на PSR и локальном HTTP |
| Fragment, userinfo, invalid percent, Unicode host, network-path | Документированное удаление fragment или ошибка до HTTP |
| Query из поля/null/списка/pagination/enricher/continuation | Отсутствие пары допустимо; добавляемая пара вызывает ошибку |
| Query API key на полном URL | Отказ, URL не изменён |
| Origin: host case/default port/subdomain/scheme/IPv6 | Предсказуемое сравнение, без доверия по DNS или суффиксу |
| Default/scoped auth, credentials body/query/form на чужом origin | Не вызываются автоматически, включая refresh после 401 |
| Explicit auth + allowlist; forceAuth без allowlist | Разрешён выбранный auth либо отказ до его вызова |
| Default headers/auth/cookies/query и cURL overrides адаптера | Никаких скрытых данных исходного клиента на внешней отправке |
| Hook/stage/auth/retry handler меняет назначение | Отказ до cache hit/HTTP, включая повторную попытку |
| Ответ 301/302/303/307/308 на другой origin | Единственный HTTP-вызов; Location не исполняется |
| Multipart upload и retry после разрешения операции | Те же байты, URL и явные headers; соблюдаются deadline и replay |
| Повторное использование execution, очистка URL, дочерние запросы | Нет изменения исходного состояния и наследования чужого разрешения |
| Custom key/identity обычного запроса и cache полного URL | Старый контракт сохранён; полный URL не обслуживается из cache |
| Debug/logger/fixture/error exports с секретом в path/query | Секрет отсутствует; отправляемый адрес не изменён |
| Не поддерживающий capability транспорт | Ошибка только для новых защищаемых режимов до отправки |

Обычные тесты — локальные fake/PSR клиенты без внешней сети. Реальные байты и redirects
проверять отдельным локальным HTTP-стендом по образцу
[TransportTimeoutIntegrationTest](../../tests/Integration/TransportTimeoutIntegrationTest.php).
Две локальные точки назначения должны доказывать отсутствие второго запроса/утечки.

После целевых тестов: `composer test -- --compact`, строгая PSR-автозагрузка для новых
типов, standalone без Laravel/Guzzle HTTP Client с поддерживающим PSR-адаптером,
PHP syntax, ссылки и `git diff --check`. Не объявлять успех на основании одного
MockTransport: он не выявляет defaults и нормализацию реального HTTP-клиента.

## Документация и совместимость

Обновить [сериализацию](../../docs/guides/serialization.md),
[auth](../../docs/guides/auth.md), [transport](../../docs/guides/transport.md),
[ошибки](../../docs/guides/errors.md) и соответствующие справочники runtime/client config.
Пример провайдера: получение upload URL → отдельный запрос с явными method/headers/body.
Тесты больших файлов не заменять этим примером: потоковый I/O вне данного объёма.

Новая возможность полного URL добавляется совместимо, но изменения внешнего
`withBaseUrl()`, отключение автоматических enrichers и требование capability для
защищаемой отправки не полностью обратно совместимы. Миграция — явные назначения
credentials/отдельный клиент и адаптер с поддержкой контракта. Относительные запросы
на исходный origin не требуют нового конфига.

Публичный changelog обновлять после реализации, без ссылок на workflow. План завершать
после проверок с фактическими результатами. Исходные issue/001 не изменять.


## Результат реализации

Выполнено 2026-09-12 по согласованным D1–D3.

- Назначение определяется в Serializer до enrichment. `RequestDestination` хранит
  origin и режим готового URL; `DestinationGuard` проверяет его до auth/cache и I/O.
  Options и PreparedRequest сохраняют контракт при копировании и пересчёте deadline.
- Добавлены `withUrl()`/`withoutUrl()`, необязательная `OriginPolicy` и явный выбор
  общих enrichers `withRequestEnrichers(bool $enabled = true)`.
- Автоматические auth/credentials/enrichers исключены из защищаемого вызова;
  explicit auth/enrichment на чужом origin требует allowlist. Query auth и cache
  не могут менять смысл готового URL. Auth refresh проверяет назначение отдельно
  и не наследует внешний URL родителя.
- `DestinationAwareInterface` подтверждается транспортом, вложенным PSR-адаптером
  и собственным retry handler. Штатные wrappers делегируют проверку; отсутствие
  поддержки обнаруживается до auth/refresh. Доверенный handler проверяет фактическую
  отправку; один вызов callback не считается подтверждением безопасности.
- Изолированный Guzzle-клиент сохраняет разрешённые сетевые настройки и не получает
  исходные auth/cookies/payload/header defaults или TLS client credentials.
  Raw cURL overrides отклоняются для защищаемых запросов.
- `ExactTargetCurlFactory` использует штатную CurlFactory и задаёт нужные параметры
  непосредственно на подготовленном handle. Это сохраняет dot-segments и пустой query,
  включая HTTP proxy, без deprecated raw-options Guzzle. Redirects не исполняются.
- Safe debug/log/recorder скрывают готовый path/query и известные отражённые ссылки.
  Raw response/exception/data остаются raw; произвольный доверенный PHP-код не sandboxed.
- Публичный контракт и миграция находятся в [гайде](../../docs/guides/external-urls.md).
  Обновлены индексы, профильные руководства и changelog без ссылок на workflow.

### Проверки

Начальное воспроизведение: 3 failed, 0 passed — полный endpoint, перенос auth через
внешний withBaseUrl и отсутствующий runtime URL override.

Итоговый набор новой приёмки — 51 passed, 137 assertions:

- [ExternalUrlContractTest](../../tests/Unit/Serialization/ExternalUrlContractTest.php):
  URL priority/reset, query conflicts, allowlist/explicit/scoped auth, forceAuth,
  default enrichment/cache bypass, 401/refresh, child origin, ошибки async/resolved,
  multipart replay, deadline, diagnostics и отрицательные входы.
- [DestinationRetryTest](../../tests/Unit/Retry/DestinationRetryTest.php): изменённый
  URL собственного handler не доходит до транспорта.
- [ExternalUrlTransportTest](../../tests/Integration/ExternalUrlTransportTest.php):
  12 реальных локальных HTTP-сценариев с двумя origin. Exact targets, cookies/auth/body/header
  defaults, 301/302/303/307/308 без второй отправки, HTTP proxy, multipart и cURL override.

`composer test -- --compact`: 1101 тест без падений — 1065 passed, 36 прежних deprecation,
3133 assertions. База 1050/2996 увеличилась на 51 тест и 137 assertions.

`composer dump-autoload --optimize --strict-psr` успешен (4921 классов).
`php -l` успешен для 34 изменённых/новых PHP-файлов.
Проверены локальные Markdown-ссылки и `git diff --check`.

Standalone с production-зависимостями успешно прошёл JSON, retry, timeout, URI и
[external URL smoke](../../tests/Support/standalone-external-url-smoke.php) без Laravel
и Guzzle HTTP Client. Воспроизведение — команда в шапке каждого smoke-скрипта;
HTTP-стенд и сценарии сохранены в репозитории, внешняя сеть не требуется.

Потоковый download/sink и экономия памяти binary upload остаются следующим отдельным
объёмом AS-08. Этот план не вводит автоматический redirect, SSRF/DNS policy или
неблокирующий async. Исходные issue/001 не изменены.
