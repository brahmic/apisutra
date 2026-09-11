# Корректная сборка URI и сериализация query

- Дата создания: 2026-09-12
- Дата обновления: 2026-09-12
- Статус: завершён

## Цель и основание

Первая приоритетная поставка AS-06 из [issue/001](../issue/001/technical-specification.md),
после [таймаутов и общего бюджета](../completed/pln-006-transport-timeouts-execution-budget.md).
Исходная версия: `8b1e034`. Пользователь согласовал D1–D3 и поручил реализацию
2026-09-12: «фиксируй, если вопросов больше нет — приступай к реализации».
Подтверждено: изменения не полностью обратно совместимы; требуется описание миграции. Это продолжение выбранного порядка «сначала критичная корректность».

Предотвратить потерю query-параметров, значений и частей пути. Сохранить zero-config:
обычный запрос должен собираться корректно без нового обязательного объекта политики,
флага включения или ручного выбора URL builder. Изменения ограничить сборкой URI и
текстовым представлением boolean; сохранить JSON, DTO, retry, deadline и cache identity.

## Основания на исходной версии

Проверены [RequestUrlBuilder](../../src/Serialization/RequestUrlBuilder.php),
[Serializer](../../src/Serialization/Serializer.php),
[RequestPartsCollector](../../src/Serialization/RequestPartsCollector.php),
[FilePayloadPreparer](../../src/Serialization/FilePayloadPreparer.php) и
[ApiKeyAuthenticator](../../src/Auth/ApiKeyAuthenticator.php).

2026-09-12 прямой вызов публичного `RequestUrlBuilder::buildUrl()` на текущей версии
воспроизвёл следующие результаты без HTTP. Это проверка builder, а не новый полный
прогон pipeline или тестов пакета.

| Вход при baseUrl `https://api.test/v1` | Фактический результат |
| --- | --- |
| `/resources?fixed=1`, query `page=2` | `/v1/resources?fixed=1?page=2` |
| `/resources`, query `enabled=false` | `/v1/resources?enabled=` |
| `/resources`, query `filter=['state'=>'open']` | `/v1/resources?filter[]=open`, ключ state потерян |
| `/resources/{id}`, path `id=null` | `/v1/resources/{id}` |
| `/resources#section`, query `page=2` | `/v1/resources#section?page=2` |
| endpoint `https://storage.test/file?a=1` | `/v1/https://storage.test/file?a=1` |

Минимальное воспроизведение из корня репозитория:

```php
<?php
declare(strict_types=1);

use Brahmic\ApiSutra\Serialization\RequestUrlBuilder;

require 'vendor/autoload.php';

$builder = new RequestUrlBuilder();
echo $builder->buildUrl(
    'https://api.test/v1',
    '/resources?fixed=1',
    [],
    ['page' => ['value' => 2], 'enabled' => ['value' => false]],
    null,
);
```

Дополнительные наблюдения по коду:

- `Query::nullable` по умолчанию **null**, поэтому наследует `serializeNulls=false`.
  В [справочнике Query](../../docs/guides/attributes/request.md) ошибочно написано,
  что дефолт атрибута false. Существующий приоритет сохраняем, документацию исправляем.
- Пустой список сейчас пропускается для Brackets/Indices/Repeat, но даёт `key=`
  для Comma. `array_values()` стирает ассоциативные и разреженные индексы.
- Multipart преобразует скалярный false в пустую строку. JSON уже сохраняет boolean;
  исправление текстового кодирования не должно менять JSON и JSON-поля multipart.
- Query API key добавляется после сборки URL через отдельную конкатенацию. Её нужно
  согласовать с объединением query и удалением fragment, сохранив idempotency retry.
- Абсолютный endpoint сейчас не имеет корректного штатного контракта. Runtime
  `withBaseUrl()` — другой механизм; обогащение credentials происходит до сборки URL,
  авторизация и hooks могут менять PreparedRequest после неё.

## Согласованный объём

Включить:

1. Сборку относительного endpoint с base path, существующим query и fragment.
2. Явное текстовое представление boolean с необязательной настройкой и точечным cast.
3. Контракт null, пустых значений и плоских query-списков без потери ключей.
4. Кодирование path-параметров и отказ до HTTP при незаполненном placeholder.
5. Проверку фактического URI на границе PSR-клиента, query auth, кеша и повторов.
6. Документацию, миграцию и changelog без ссылок на workflow.

По D3 отдельно реализовать внешние и подписанные URL вместе с соответствующей
частью AS-08. В этом плане определить их границу и явную ошибку неподдерживаемого входа,
не заявляя полное закрытие AS-06. Streaming/sink, origin policy, новый async, переработка
DTO и новый form-urlencoded payload builder не входят в эту поставку.

## Согласованные решения

### D1. Boolean без обязательной настройки

**Принято: `true → 1`, `false → 0` по умолчанию.** Это исправляет false и сохраняет
нынешнее представление true. Поддержать также явный выбор `true/false` для API,
которые требуют слова. Публичные имена: `BooleanFormat::Numeric/Literal` и
необязательное поле `ClientConfig::textBooleanFormat` с дефолтом Numeric.

Область — query и скалярные текстовые поля multipart. JSON и сериализация DTO сохраняют
свои типы и существующие политики. Порядок: действующий cast → форматирование оставшегося
boolean → URL-кодирование/запись текста. Строки `"false"`, `"0"`, `""`, возвращённые cast,
повторно не интерпретируются. Для точечного выбора использовать существующий механизм
`#[Cast]`, расширить существующий `BooleanCast` необязательным форматом; новый runtime override не нужен.
Правило применяется и к boolean-элементам плоских query-списков.

Миграция: провайдер, которому нужна прежняя пустая строка для false, задаёт это явным
cast. Не добавлять обязательный режим совместимости всем клиентам.

### D2. Пустые значения и структура query

**Принято: сохранить действующий выбор null и поддерживать плоские списки;
неподдерживаемые структуры отклонять до HTTP.**

| Значение | Согласованное поведение |
| --- | --- |
| Параметр не собран/исключён правилами сериализации | Не отправлять |
| Верхнеуровневый null | По умолчанию пропустить; при `Query(nullable: true)` или наследуемом `serializeNulls=true` — `key=` |
| `false` / `true` | `0` / `1` по D1 либо явно выбранный формат |
| `0` / `"0"` | `key=0`, без truthy-фильтрации |
| Пустая строка | `key=` |
| Пустой список | Пропустить для всех четырёх QueryArrayFormat |
| Плоский список | Сохранить порядок, дубликаты и выбранный Brackets/Indices/Repeat/Comma |
| Null внутри плоского списка | Сохранить позицию как пустое значение; правило пропуска верхнеуровневого null не удаляет элементы списка |
| Ассоциативный, разреженный либо вложенный массив | `serialization_error`, без `array_values()` и строк `Array`; явный JsonCast или отдельные именованные Query-поля для нужного протокола |

Различать значения на входе не означает обещать универсальное различие в URI:
включённый null и пустая строка имеют одинаковое `key=`, пустой список и отсутствие
параметра — отсутствие пары. Это документированный выбор, без новых настроек.
Comma сохраняет разделение запятой; список со строкой, содержащей запятую, неоднозначен
для этого формата. Принято отклонять его и использовать Repeat/Brackets либо явный
cast по контракту провайдера. JSON-строка через JsonCast остаётся обычным скаляром.

### D3. Относительные, абсолютные и подписанные URL

**Принято: первую поставку ограничить относительными endpoint.** Абсолютный endpoint
или network-path `//host/path` отклонять с `configuration_error` до auth/HTTP вместо
формирования повреждённого адреса. Это не запрет действующего `withBaseUrl()`;
его независимость от исходного execution/config сохраняется.

Отдельный следующий контракт AS-06/AS-08 должен предусмотреть:

- явную передачу полного URL без дописывания base path;
- сохранение подписываемых path/query байтов, `%2F`, `+`, порядка и повторяющихся ключей;
- режим без пересборки query и отказ при попытке дополнить его через поля, pagination,
  enrichers или query auth; отсутствие незаметного изменения URL поздними hooks;
- автоматическое отсутствие переноса credentials исходного origin на чужой origin,
  охватывающее auth, query/body enrichment и заголовки конфигурации;
- отдельное явное разрешение междоменной передачи credentials, которое не заменяется
  `forceAuth()`; прежнюю redirect policy и ограничения фактического транспорта.

Существующий `withBaseUrl()` также должен попасть в этот будущий анализ origin policy.
Первая поставка не является исправлением всей безопасности междоменных вызовов и
не обещает поддержку signed URL через workaround с baseUrl. Введение поддержки
внешнего URL только в builder без этих правил создаст некорректный публичный контракт.

## Контракт сборки для первой поставки

Применить согласованные правила D1–D3:

- Сохранить семантику base path: `https://api.test/v1` + `/users` или `users`
  даёт `/v1/users`. Ведущий slash endpoint не сбрасывает `/v1`.
- Разделить path/query/fragment до соединения частей. Query base URL, query endpoint
  и сгенерированные пары соединять в этом порядке через `&`, с одним разделителем `?`.
  Fragment удалить до auth и транспорта; query auth не должен попасть после `#`.
- Существующие query-строки не прогонять через `parse_str`/`http_build_query`, не
  сортировать и не декодировать/кодировать повторно. Сохранить повторяющиеся ключи,
  `+`, `%20`, `%2F`, пустые значения и порядок; сгенерированные значения кодировать один раз.
  Пустой `?`/граничный `&` не должны создавать второй разделитель.
- Совпадающие ключи из разных источников сохранять повторяющимися парами в указанном
  порядке. Не обещать «runtime всегда перезаписывает fixed query»: толкование повторов
  зависит от API. Не вводить скрытый first/last-wins в SDK.
- Подставлять параметры только в path, не в host/query/fragment. Параметр — исходное
  значение сегмента: `/` → `%2F`, `%` → `%25`, пробел → `%20`, `+` → `%2B`.
  Уже закодированная строка `%2F` как значение означает буквальные символы `%2F` и
  станет `%252F`; эвристическое rawurldecode запрещено. Литералы `%2F` в готовом path
  endpoint повторно не кодировать.
- Отсутствующий/null path-параметр — `serialization_error` до HTTP. Значение `0`
  допустимо; пустое значение и отдельные сегменты `.`/`..` отклонять
  вместо неявного изменения маршрута. Не вводить разрешение parent-directory paths
  как побочный эффект применения готового URI resolver.
- Ошибки формы URL/неподдерживаемого режима — `configuration_error`; ошибки значений
  параметров — `serialization_error`. Сохранить result-first и доставку через
  raw/resolved/dataOrFail/throwOnErrors, не включать retry для этих ошибок.

## Минимальный поток и изменения

1. `RequestPartsCollector` сохраняет типы, применяет имеющиеся casts и выбор null.
   Не менять routing Query/Body, naming, enum/date-time policies и pagination overrides.
2. Небольшой общий formatter кодирует текстовые boolean. Для multipart подключить
   эффективную настройку к `FilePayloadPreparer`, не превращая JSON в текстовый формат.
3. `RequestUrlBuilder` разделяет компоненты URI, подставляет path и добавляет пары query.
   Переиспользовать уже установленный PSR-7 пакет там, где он сохраняет согласованные
   байты и семантику base path. Новая библиотека или обязательный контейнер не нужны.
4. `ApiKeyAuthenticator` использует то же правило добавления query; протестировать
   повторную авторизацию, чтобы не накапливать credential-пары при retry/401.
   Не менять provider-specific правила замены уже заданного ключа без отдельного решения.
5. Проверить копии PreparedRequest и RequestOptions, кеш-ключи, redaction и передачу
   transportOptions/deadline. Сформированный URI должен одинаково доходить до MockTransport
   и PSR-клиента, за исключением явной нормализации, оговорённой контрактом.

## Порядок выполнения

1. Согласовать D1–D3, включая ограничения массивов и отложенную поддержку внешних URL.
   Обновить этот план по ответам; только затем переводить в «в работе».
2. Написать воспроизводящие тесты R07/R08 и граничные сценарии. Сначала проверить их
   на исходной версии, записав наблюдаемую ошибку, затем реализовать исправления.
3. Реализовать сборку URI, path validation и общий append query; подключить query auth.
4. Реализовать D1/D2, проверить BooleanFormat/cast, совместимость JSON и multipart.
5. Выполнить приёмку ниже, обновить профильные guides и changelog; указать границу
   AS-06/AS-08, новые дефолты и точечную миграцию.

## Приёмка

Проверять полные строки URI и байты multipart; `parse_str()` в ожиданиях недостаточен,
потому что скрывает потерю повторяющихся ключей и изменение кодирования.

| Сценарий | Ожидаемый результат |
| --- | --- |
| `/resources?fixed=1` + `page=2` | Один `?`, пары `fixed=1&page=2` |
| Base path, trailing slash, query в base и endpoint, fragment | Согласованное соединение компонентов; fragment отсутствует на границе HTTP |
| Boolean с дефолтом, конфигом и cast, также в списке/multipart | Numeric/Literal по D1; явная строка cast сохранена |
| null, false, 0, `"0"`, `""`, [] | Таблица D2, `Query(nullable)` перекрывает глобальный выбор null |
| Четыре формата плоских списков, повторы, null-элемент | Порядок и позиции сохранены; нет случайных приведений типов |
| Map, sparse list, nested array, неподдерживаемое значение | Явный `serialization_error`, HTTP не начинается |
| Path Unicode, пробел, `/`, `+`, `%`, 0, отсутствующее значение | Кодирование одного сегмента либо явная ошибка, без скрытого декодирования |
| Исходный query с повтором ключа, `%2F`, `+`, совпадающим новым ключом | Существующие байты и порядок сохранены, новые пары добавлены по контракту |
| Absolute endpoint, `//host/path`, неподдерживаемая схема | Ошибка конфигурации до auth/HTTP по D3 |
| GET/POST/PUT/PATCH/DELETE с явным Query | Одинаковая сборка URI при сохранении различий body routing |
| Query API key, 401 refresh и обычный retry | Корректный URL, нет накопления одинаковых credential-пар |
| Кеш и диагностика | Различающиеся boolean-запросы не объединяются случайно; custom key внутри identity сохраняет согласованную семантику, секреты маскируются |
| Runtime withBaseUrl / повторное использование execution | Исходные request/config и другие вызовы не изменены |

Основные существующие регрессии:
[SerializerTest](../../tests/Unit/Serialization/SerializerTest.php),
[SerializerFilesTest](../../tests/Unit/Serialization/SerializerFilesTest.php),
[credentials enrichment](../../tests/Unit/Serialization/CredentialsEnrichmentTest.php),
[request defaults](../../tests/Unit/Serialization/SerializerRequestDefaultsTest.php),
[HttpTransport](../../tests/Unit/Core/HttpTransportTest.php),
[auth/401](../../tests/Unit/Retry/RetrySenderAuthRefreshTest.php),
[таймауты](../../tests/Unit/Timing).

Проверки реализации: затронутые тесты → `composer test -- --compact`; строгая
PSR-автозагрузка при новых типах; standalone без Laravel/Guzzle HTTP Client;
ссылки и `git diff --check`. Сравнить фактический request target через PSR-клиент;
при сомнениях в нормализации адаптера добавить локальный HTTP-стенд без внешней сети.

Документация: [serialization](../../docs/guides/serialization.md),
[настройки](../../docs/guides/client-config/serialization.md),
[атрибуты запросов](../../docs/guides/attributes/request.md),
[auth](../../docs/guides/auth.md), [transport](../../docs/guides/transport.md),
[errors](../../docs/guides/errors.md). При появлении отдельного гайда обновить индексы.

План завершать после подтверждённой приёмки и записи результатов. Исходные issue/001 не изменять. Код, тесты, публичная документация и changelog
обновляются в пределах согласованной поставки. Поддержка signed/external URL остаётся
предложенным отдельным этапом, а не выполненной частью AS-06.


## Результат реализации и проверки

Выполнено 2026-09-12. Контракт D1–D3 реализован без обязательных настроек:

- `UrlQuery` соединяет исходные query-байты; `RequestUrlBuilder` разделяет компоненты,
  проверяет относительный endpoint, подставляет path и валидирует query-значения.
- `BooleanFormat` используется в query и текстовом multipart; `ClientConfig.with()`
  сохраняет формат. У уже существующего `BooleanCast` добавлен необязательный формат
  исходящего текста; конструктор без аргументов и гидрация сохраняют прежнее поведение.
- Query auth отслеживает собственную пару по позиции и хешу в метаданных копии
  PreparedRequest. Повторная авторизация заменяет эту пару, не удаляя исходные дубли.
- JSON/DTO, runtime withBaseUrl, cache identity/custom key, retry и deadline сохранены.
  Исправлена документация nullable; описаны ограничения и точечная миграция.

Воспроизведение на исходном коде: 17 failed, 4 passed в начальном наборе URI-тестов.
Итоговая приёмка:

- [RequestUrlContractTest](../../tests/Unit/Serialization/RequestUrlContractTest.php) и
  [UriPipelineTest](../../tests/Unit/Serialization/UriPipelineTest.php): 64 passed,
  128 assertions. Проверены четыре формата, null/false/нули, invalid input до HTTP,
  байты path/query на PSR-границе, 401/503 без накопления API key, multipart replay,
  deadline options, независимость withBaseUrl, cache false/empty, redaction и async.
- `composer test -- --compact`: 1050 без падений — 1014 passed, 36 прежних deprecation,
  2996 assertions. Исходная база — 986 тестов, 2868 assertions; добавлены 64 сценария.
- `composer dump-autoload --optimize --strict-psr`: успешно.
- `php -l`: все 15 изменённых/новых PHP-файлов без синтаксических ошибок.
- Standalone с production-зависимостями: JSON, retry, timeout и
  [URI smoke](../../tests/Support/standalone-uri-smoke.php) успешно без Laravel и
  Guzzle HTTP Client. Команды воспроизведения указаны в smoke-скриптах.
- Проверены Markdown-ссылки изменённых документов и `git diff --check`.

Публичные документация и changelog не ссылаются на workflow. Issue/001 не изменён.
Это завершение первой поставки AS-06; внешние/подписанные URL и origin/credential policy
AS-08 остаются отдельным последующим контрактом.
