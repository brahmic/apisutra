# Замена и очистка тела PreparedRequest

- Дата создания: 2026-09-12
- Дата обновления: 2026-09-12
- Статус: завершён

## Цель и основание

Закрыть подтверждённую часть AS-09: неоднозначное тело подготовленного запроса
и невозможность его явно очистить. Основание —
[issue/001, AS-09](../issue/001/technical-specification.md#12-as-09--runtime-опции-и-авторизация),
[первая поставка AS-09](../completed/pln-002-cache-auth-diagnostics.md)
и [потоковые файлы](../completed/pln-009-streaming-file-transfers.md).
Проверенная исходная версия — `a3462b4`.

Пользователь согласился начать с узкой проверки body/stream и включать остальные
runtime-опции только при подтверждённых проблемах. D1–D2 согласованы пользователем 2026-09-12; реализация поручена и завершена.

Основной сценарий: hook или расширение меняет уже подготовленный файловый запрос
на строковое тело либо удаляет тело. Транспорт должен отправить выбранное содержимое,
не сохраняя прежний поток как скрытый приоритетный источник.
Обычная сериализация DTO/файлов не требует новых настроек; конфиг клиента не меняется.

## Результаты точечной проверки

| ID | Статус | Наблюдение и основание |
| --- | --- | --- |
| B1 | Воспроизведено | `PreparedRequest(stream: old)->with(body: new)` оставляет оба поля. [HttpTransport](../../src/Transport/HttpTransport.php) отправляет `old-file`, хотя `body = new-string`. |
| B2 | Воспроизведено | `with(stream: null)` сохраняет поток; аналогичная конструкция `body: $body ?? $this->body` не позволяет очистить строку. Источник — [PreparedRequest](../../src/VO/Http/PreparedRequest.php). |
| B3 | Воспроизведено | Конструктор принимает одновременно непустые `body` и `stream`; транспорт молча выбирает поток. Конфликт не диагностируется. |
| B4 | Наблюдение по коду | `with()` копирует заголовки, не связывая их с новым телом. При замене могут остаться прежние Content-Length/Transfer-Encoding и Content-Type multipart. Нужен явный контракт D2 и HTTP-регрессия. |
| B5 | Наблюдение по коду | [RequestFlowRunner](../../src/Pipeline/Flow/RequestFlowRunner.php) сохраняет локальный `$prepared` до BeforeSend hook, а hook может изменить `context->preparedRequest`. [ExecutionResult::requestDebug](../../src/Result/ExecutionResult.php) читает также исходный `meta.body`. Проверить, что debug после замены показывает отправленное тело, а не прежнее. |
| R1 | Проверено, отправка не нарушена | `withRateLimit(2, 60)->withoutRateLimit()` оставляет внутренний override 2, но disabled=true. [RateLimitApplier](../../src/Pipeline/Transport/RateLimitApplier.php) не обращается к backend: cacheGet/cacheSet=null. Это не доказанный дефект действующего ограничения. |
| R2 | Проверено, работает | Сброс auth scope возвращает null; повторный `withTimeout(20)` очищает connect override; `withCache()` сохраняет TTL 120. Исходная копия сохраняет scope secondary и connect=7. TTL-семантика уже принята ранее. |

Проба использовала `HttpTransport` и тестовый PSR-клиент, читающий фактически
переданное ему тело. Внешний HTTP не выполнялся. Результаты B1–B3:

```text
replace: body="new-string", hasStream=true, sent=["old-file"]
clear:   body=null,         hasStream=true, sent=["old-file"]
both:    body="new-string", hasStream=true, sent=["old-file"]
```

Воспроизведение из корня репозитория:

```bash
php <<'PHP'
<?php
require 'vendor/autoload.php';
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\Transport\HttpTransport;
use Brahmic\ApiSutra\Tests\Stubs\Retry\ConsumingHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
$factory = new HttpFactory();
foreach (['replace', 'clear', 'both'] as $case) {
    $original = new PreparedRequest(HttpMethod::POST, 'https://fixture.test', stream: Utils::streamFor('old-file'));
    $changed = match ($case) {
        'replace' => $original->with(body: 'new-string'),
        'clear' => $original->with(stream: null),
        'both' => new PreparedRequest(HttpMethod::POST, 'https://fixture.test', body: 'new-string', stream: Utils::streamFor('old-file')),
    };
    $http = new ConsumingHttpClient([new Response(204)]);
    (new HttpTransport($http, $factory, $factory))->send($changed);
    echo json_encode(['case' => $case, 'body' => $changed->body, 'hasStream' => $changed->stream !== null, 'sent' => $http->bodies]), "\n";
}
PHP
```

Запущены существующие проверки:

```bash
vendor/bin/pest tests/Unit/Core/HttpTransportTest.php tests/Unit/RateLimit/RateLimitApplierTest.php tests/Unit/Timing/TimeoutContractTest.php tests/Unit/Auth/AuthScopeResetTest.php --compact
```

Результат: 28 passed, 103 assertions. Это проверка исходной версии и принятых
контрактов, а не тестирование будущей реализации. Код и публичная документация
при подготовке плана не изменялись; полный повторный аудит не проводился.

## D1 — один источник тела и явная очистка

Принято ограничить `PreparedRequest` тремя валидными состояниями:
строка (`body !== null`, включая `''`), поток (`stream !== null`) либо отсутствие
тела (оба null). Одновременные строка и поток запрещены даже для пустой строки.

Согласованный API только на `PreparedRequest`, без новых методов на обычном request:

- `withBody(string $body): self` — выбрать строку и убрать прежний поток;
- `withStream(StreamInterface $stream): self` — выбрать поток и убрать прежнюю строку;
- `withoutBody(): self` — убрать всё HTTP-тело, обнулить оба представления.

Отдельный `withoutStream()` не нужен: поток является одним из представлений тела.
Методы возвращают новую копию. Они не читают, не перематывают, не закрывают поток
и не создают копию содержимого; ранее созданный объект остаётся неизменным.
Заимствование и владение из файлового контракта сохраняются.

Существующий общий `with()` использует тот же механизм переключения:

| Вызов | Согласованное поведение |
| --- | --- |
| `with(body: 'new')` | Строка заменяет всё тело, прежний stream очищается. |
| `with(body: '')` | Выбирается пустая строка; это не отсутствие изменения. |
| `with(stream: $stream)` | Поток заменяет всё тело, прежний body очищается. |
| `with(body: 'new', stream: $stream)` | `ConfigurationException`: выбор неоднозначен. |
| `with(body: null)` / `with(stream: null)` | Прежняя семантика «не менять»; для очистки есть withoutBody(). |
| `with(headers: ...)` / `with(url: ...)` | Без выбора нового тела сохраняются оба прежних валидных поля. |

Конструктор также отвергает конфликт. В прямом low-level вызове ошибка выбрасывается
при создании/копировании; внутри pipeline действует принятый result-first/throwOnErrors
и классификация ошибок hook. Последняя проверка перед фактическим HTTP не допускает
неоднозначного тела от собственного адаптера/расширения. Если конфликт возник в hook
после авторизации, нельзя обещать отсутствие уже выполненной auth; гарантируется
отсутствие отправки некорректного тела.

Причина: новые методы дают явный способ очистки, а общий `with(body: ...)` больше
не оставляет ловушку с приоритетом старого stream. Смена значения null не нужна.

## D2 — заголовки, metadata и контекст выполнения

Замена тела включает связанные проверки, чтобы исправление B1 не оставило
неправильную длину или показ старых данных.

### Заголовки

- При выборе/очистке тела убрать унаследованные Content-Length и Transfer-Encoding,
  независимо от регистра имени. HTTP-адаптер определяет framing для нового тела;
  неизвестная длина потока не вычисляется чтением всего файла.
- Если в том же общем `with()` передан явный новый массив headers, сохранить
  его как новый полный снимок, как работает текущий API. Явную Content-Length
  сверять с известным размером передаваемого тела перед HTTP; несовпадение и
  одновременная Content-Length/Transfer-Encoding дают configuration_error.
  При неизвестной длине не читать поток ради проверки: ответственность за явно
  заданную длину остаётся у вызывающего адаптера/SDK провайдера.
- Content-Type и остальные прикладные заголовки сохранять. Не определять JSON/MIME
  по содержимому строки. При multipart → JSON hook явно задаёт новый Content-Type,
  например `withBody($json)->withHeader('Content-Type', 'application/json')`.
  Собственные digest/подписи тела пересчитывает их владелец; смена payload не
  означает, что SDK способен переподписать произвольный внешний протокол.

### Metadata, debug, cache и защита назначения

- При явной замене/очистке не показывать прежний структурированный `meta.body`
  как текущее тело. Удалить устаревший снимок body/bodyIsRoot; не декодировать новую
  строку и не читать поток ради его восстановления. Raw debug содержит выбранную
  строку либо hasStream=true; безопасная redaction сохраняется.
- Для итогового debug использовать фактически отправленный `ProviderResponse.request`,
  а при отсутствии HTTP-ответа — актуальный context prepared request. Проверить
  BeforeSend hook и последнюю retry-попытку; не делать отдельную систему audit export.
- Файловые метаданные происхождения, консервативный запрет кеша файловых операций
  и `FileTransferOptions` не сбрасывать автоматически при очистке тела.
  Удаление upload-тела не должно включать кеш ранее файловой операции.
- `RequestDestination`, download intent/target, auth policy и execution budget
  сохраняются. Не добавлять общий механизм очистки всех nullable полей
  PreparedRequest: это могло бы снять гарантии origin или файлового сохранения.
- [FileTransferGuard](../../src/Files/FileTransferGuard.php) и
  [DestinationGuard](../../src/Http/DestinationGuard.php) продолжают защищать
  исходный контекст. Новому потоку требуется streaming capability транспорта,
  даже если поток добавлен hook после исходной сериализации.
- [RequestBodyReplay](../../src/Retry/RequestBodyReplay.php) запоминает окончательное
  тело первой отправки. Его замена/очистка между попытками должна сохранять отказ
  `body_changed`, исходную ошибку и отсутствие второй отправки.

## Что не включено из runtime-опций

Проверка не дала основания для массового изменения `RequestOptions::with()`:

- `withoutRateLimit()` отключает ограничение за счёт явного disabled-флага.
  Очистка неиспользуемого внутреннего override может быть отдельной уборкой,
  но не требует расширения этого плана или новых методов возврата к наследованию.
- Auth scope и nullable connect timeout уже очищаются через проверку наличия
  ключа; URL/download target также имеют явный reset. Их контракт сохраняется.
- Сохранение TTL при `withCache()` намеренно и проверено тестами.
- Отсутствие отдельных reset-методов для idempotency key, pagination, traceId,
  timeout и других опций само по себе не доказывает ошибку отправки. Новые API
  для них сейчас не предлагаются. Полная замена опций через `withOptions()` уже
  существует, но не объявляется автоматически сбросом всех иных уровней fallback.

Auth refresh locks, worker lifecycle, распределённый rate-limit и полный остаток
AS-09 остаются отдельными задачами. Этот план их не закрывает.

## Совместимость

Обычные запросы с одним источником тела сохраняют HTTP-байты и не требуют миграции.
Новых обязательных конфигов, флагов совместимости или абстракции body-контейнера нет.

Изменяются два ранее неоднозначных случая: `with(body: ...)` поверх stream начинает
отправлять выбранную строку, а одновременные body/stream становятся ошибкой.
Для разработчика hook/адаптера это изменение поведения; migration note обязателен.
`with(...: null)` не получает нового значения. `withoutBody()` — явная новая операция.

При замене тела старые framing headers удаляются; явно заданные новые проверяются
по D2. Content-Type не угадывается автоматически. Нужны примеры смены multipart на
JSON и очистки тела с сохранением URL, auth, download target и прочих options.

## Шаги реализации

1. Согласовать D1–D2 и изменение совместимости. Сначала превратить B1–B3 в
   регрессионные тесты наблюдаемых HTTP-байтов; для B4–B5 добавить отдельные
   воспроизводящие сценарии до исправления.
2. Реализовать единый механизм переключения/очистки в PreparedRequest, новые
   методы и запрет неоднозначной формы. Не добавлять sentinel ко всем публичным
   аргументам и не переписывать nullable-copy всего пакета.
3. Обработать framing и устаревший снимок metadata. Провести проверки через
   штатный HttpTransport/Guzzle и pipeline hooks, включая result-first ошибки.
4. Проверить guard/retry/cache/debug: смена тела до первой отправки разрешена,
   между попытками вызывает body_changed; origin, target, budget и защита файлов
   не теряются. Исправить B5 в пределах итогового request debug при подтверждении тестом.
5. Обновить [транспорт](../../docs/guides/transport.md),
   [файлы](../../docs/guides/files.md),
   [pipeline](../../docs/guides/request-pipeline.md),
   [повторы](../../docs/guides/retries-rate-limit.md) и
   [changelog](../../CHANEGLOG.md) по реализованному контракту, без workflow-ссылок.
6. Выполнить затронутые тесты, полный composer test, синтаксис и проверки ссылок;
   при изменении автозагрузки — strict PSR dump. Проверить standalone без Laravel
   и Guzzle HTTP Client. Файловую проверку памяти запускать при изменениях,
   затрагивающих чтение/копирование потоков, а не ради новых getter-методов.

## Критерии завершения

- Матрица none/string/empty-string/stream: переходы в обе стороны, очистка,
  повторная очистка и независимость исходной копии. Copy не двигает позицию
  потока, не закрывает его и не материализует байты.
- B1 отправляет `new-string`, а не `old-file`; после withoutBody прежний файл
  не отправляется. Конфликт конструктора/общего with отклоняется до HTTP.
  Legacy with(null) сохраняет прежнее значение, как документировано.
- Реальный локальный HTTP получает выбранные байты и корректную длину после
  замены/очистки. Проверены заголовки разного регистра, неизвестный размер
  потока, явная неверная длина, сохранение/явная замена Content-Type.
- BeforeSend hook меняет тело, и debug/recorder отражают фактический запрос;
  redaction и маскирование signed URL не ослаблены. Поток ради debug не читается.
- Замена/очистка между попытками даёт body_changed и не отправляет второе тело;
  неизменённый seekable поток повторяется с прежними байтами.
- Сохраняются file cache exclusion, origin policy, download target, capability
  транспорта и общий бюджет. Смена тела не используется как способ сбросить защиту.
- 28 исходных проверок runtime/auth/timeout/транспорта продолжают проходить;
  полная регрессия и standalone успешны. Опубликованы точные результаты и миграция,
  план переносится в completed только после реализации и приёмки.

## Вывод по объёму

Следующая поставка — управление телом PreparedRequest и непосредственно связанные
с ним framing/debug проверки. Отдельный аудит всего SDK и общий рефакторинг
runtime-опций не требуются. Реализация согласованного контракта завершена и проверена.


## Результаты реализации — 2026-09-12

D1–D2 подтверждены пользователем перед реализацией. `PreparedRequest` использует
единый механизм выбора/очистки тела; null-семантика общего `with()` сохранена.
Добавлен [RequestBodyGuard](../../src/Http/RequestBodyGuard.php), который проверяет
конфликт источников и явный framing перед HTTP, в том числе в retry pipeline.
Проверка размера использует метаданные потока и позицию, не читает содержимое;
неизвестный размер не материализуется. Другие runtime-опции не менялись.

B1–B4 воспроизведены падающими регрессиями до исправления: прежний поток фактически
отправлялся вместо строки, очистка отсутствовала, конфликт не отклонялся, framing
и metadata оставались прежними. B5 также подтверждён отдельным тестом: после
BeforeSend HTTP получил новую строку, но итоговый debug возвращал bodyRaw=null.
Теперь debug успеха и ошибки получает запрос ответа, а без ответа — актуальный
контекст. В ошибочном результате также применяется redaction клиента.

Уточнение по существующей классификации: `ConfigurationException` внутри hook
возвращает `configuration_error` (эта ветка уже имеет приоритет над failureCode hook).
При `throwOnErrors` выбрасывается исходное исключение. Классификация не изменена.
Изменение тела после ответа по-прежнему останавливает retry с `body_changed`.

Проверки добавлены в [контракт тела](../../tests/Unit/Core/PreparedRequestBodyTest.php),
[контракт pipeline](../../tests/Unit/Pipeline/PreparedBodyContractTest.php) и
[повторы потоков](../../tests/Unit/Retry/StreamReplayContractTest.php).
Две старые диагностические fixture одновременно задавали body/stream; их разделили
на строковый и потоковый варианты с сохранением проверки redaction и позиции.

- `composer test`: **1198 тестов без падений — 1162 passed, 36 прежних deprecation,
  3604 assertions**, 8.23 s. Включены исходные runtime/auth/timeout сценарии.
- Локальный HTTP через штатный Guzzle получил выбранные байты и правильный
  Content-Length после замены/очистки; старый Transfer-Encoding удалён.
- Проверены все переходы none/string/empty-string/stream, неизменность исходной
  копии, неизвестная длина, явные ошибки framing, BeforeSend, последний retry,
  HTTP-ошибка и сетевой сбой, recorder и redaction.
- Проверены файловый cache bypass после очистки, streaming capability нового потока,
  auth, сохранение бюджета/download target и запрет изменения подписанного URL.
- Синтаксис 14 изменённых/новых PHP-файлов корректен; strict PSR autoload — 4934 класса.
- Standalone с production-зависимостями, без Laravel/Guzzle HTTP Client: новый
  [smoke тела](../../tests/Support/standalone-body-smoke.php), существующие
  [файлы](../../tests/Support/standalone-streaming-smoke.php) и
  [внешние URL](../../tests/Support/standalone-external-url-smoke.php) прошли.
  Команды запуска указаны в шапках скриптов.
- Публичные руководства и changelog обновлены без ссылок на workflow; локальные
  Markdown-ссылки и `git diff --check` проверены. Чтение/копирование файлов не
  менялось, поэтому отдельный memory smoke повторно не запускался.

AS-09 целиком не объявляется закрытым: auth refresh locks, worker lifecycle и иные
runtime-опции остаются вне объёма. Исходный `.workflow/issue/` не изменялся.
