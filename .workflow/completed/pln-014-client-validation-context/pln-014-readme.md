# Валидация в контексте клиента и явная недоступность проверок

- Дата создания: 2026-09-12
- Дата обновления: 2026-09-12
- Статус: завершён

## Цель и основание

Исправить [F07 исходного аудита](../../audit/aud-001-architecture-code-2026-09-11/aud-001-readme.md#f07-настройка-контейнера-клиента-не-применяется-к-валидации):
применять фабрику валидации выбранного SDK-клиента. Уточнить ограниченную часть
[AS-12, требования 2 и 5–7](../../issue/001/technical-specification.md#15-as-12--laravel-интеграция)
о недоступных зависимостях и независимости клиентов в одном процессе.
Ядро остаётся агностичным к API провайдера и работает без приложения Laravel.

Проверка и подготовка плана поручены пользователем 2026-09-12. Проверен коммит
`6d5aed1` с завершённой гидратацией DTO; исходники, тесты и публичные документы
при подготовке не менялись. Сохранены [SHA-256 проверенных файлов](artifacts/source-sha256.txt).
Решения D1–D3 приняты пользователем 2026-09-12 после разбора продуктовых примеров
(ответ «Принято»). Согласованы приоритет клиента, ошибка при недоступных проверках
и контекст ручной валидации с сохранением zero-config. Реализация начата по поручению пользователя 2026-09-12.

## Проверка и воспроизводимость

Прочитаны Validator, PipelineValidator, ValidatesAttributes, реестр и адаптеры
контейнера, ClientConfig, порядок pipeline, тесты и публичные руководства.
Проверки выполнялись на PHP 8.5.4, 64-bit, через MockTransport; реальный HTTP,
база данных и внешние API не использовались. Архив history не привлекался.

[Probe](artifacts/probe.php) использует настоящую Illuminate Validation Factory,
тестовое правило `fixture_rule` с разным результатом у фабрик и именованные
[provider](artifacts/ProbeProvider.php), [request](artifacts/ProbeRequest.php),
[DTO](artifacts/ProbeDto.php). Factory calls и HTTP calls измеряются отдельно;
проверяются pipeline и ручной `isValid()` привязанного запроса.

```bash
php -d display_errors=stderr \
  .workflow/completed/pln-014-client-validation-context/artifacts/probe.php
```

[Baseline](artifacts/baseline.jsonl): **14 записей**. Deprecated-вызов
ReflectionMethod::setAccessible из текущего Validator на PHP 8.5 выводится в stderr;
JSONL содержит только наблюдения. Probe сбрасывает static factory через reflection
в собственном процессе, поскольку публичного reset сейчас нет. Это средство
воспроизведения, не предлагаемый способ настройки SDK.

[Standalone probe](artifacts/standalone-probe.php) запускается с отдельной копией
текущих src и production dependencies, установленными с `--no-dev`:

```bash
php -d display_errors=stderr \
  .workflow/completed/pln-014-client-validation-context/artifacts/standalone-probe.php \
  /path/to/no-dev-checkout
```

[Standalone baseline](artifacts/standalone-baseline.jsonl): **4 записи**.
Скрипт проверяет отсутствие Illuminate Container и Validation Factory; зависимости
основной dev-установки для загрузки ядра не используются.

Базовый прогон затронутых тестов:

```bash
vendor/bin/pest tests/Unit/Core/ValidatorTest.php \
  tests/Unit/Pipeline/PipelineValidatorTest.php \
  tests/Unit/Pipeline/PipelineValidationOrderTest.php \
  tests/Unit/Pipeline/RequestContractValidatorTest.php \
  tests/Unit/Support/ContainerProviderRegistryTest.php \
  tests/Unit/Core/ContainerProviderRequestResolverTest.php \
  tests/Unit/Laravel/SdkServiceProviderTest.php
```

Результат: **27 тестов без падений (11 passed, 16 deprecated), 59 assertions**, 0.15 s.
Это исходная проверка выбранных контрактов, не полный suite и не приёмка исправления.
Существующие тесты проверяют глобальный provider, но не приоритет provider клиента.

## Находки и действующие ограничения

| ID | Основание и наблюдение | Значение для плана |
| --- | --- | --- |
| V1 / P1, воспроизведено | `client_only_reject`: фабрика клиента должна отклонить значение, но HTTP вызван один раз, результат success, factory calls = 0. При глобальной регистрации такой фабрики — validation_failed, HTTP = 0. То же игнорирование в promise API. | Передать контекст клиента в валидацию. |
| V2 / P1, воспроизведено | `global_accept_client_reject`, обратный сценарий и `static_reject_client_accept`: решение принимает глобальная/static фабрика. В A→B→A все запросы проходят при отсутствующем глобальном валидаторе; ни одна клиентская фабрика не вызвана. | Явно установить приоритет и не подменять provider клиента общим состоянием. Это различие настроек SDK-клиентов, не встроенная модель аккаунтов. |
| V3 / совместимость, воспроизведено | Без фабрики `#[Validate]` пропускается в pipeline и `DTO::isValid()`. Без Illuminate запрос с правилами отправлен; request без правил работает; custom preflight возвращает validation_failed без HTTP. | Пропуск прямо описан в текущей документации. Его замена ошибкой — продуктовое изменение D2, а не совместимый рефакторинг. |
| V4 / P2, воспроизведено | Custom provider возвращает stdClass вместо Factory: правила молча пропускаются. | Неправильную фабрику отличать от валидных данных; configuration_error. |
| V5 / наблюдение | Validator::useFactory имеет глобальную область и не допускает null. ContainerProviderRegistry::reset не очищает её; probe подтверждает сохранение. TestTrait также не очищает factory. | Глобальная область bootstrap сама по себе допустима. Добавить отдельный явный reset и изоляцию тестов; не обещать автоматический сброс всех сервисов через реестр контейнера. Падение suite от порядка не установлено. |
| C1 / положительный контроль | Auto-detect настоящего Illuminate Container с `validator` вызывает правильную фабрику и блокирует HTTP. | Сохранить zero-config в обычном Laravel-окружении; не заставлять повторно передавать factory в ClientConfig. |

Текущий [Validator](../../../src/VO/Validation/Validator.php) сначала собирает rules,
inputs, labels и messages. При отсутствии правил он возвращает успех без фабрики.
Затем выбирает static factory или глобальный ContainerProviderRegistry.
[PipelineValidator](../../../src/Pipeline/Flow/PipelineValidator.php) имеет PipelineContext,
но вызывает `Validator::check($request)` без него. [ValidatesAttributes](../../../src/Traits/ValidatesAttributes.php)
пользуется тем же вызовом для requests и DTO.

Порядок pipeline: attributes → custom preflight → RequestContractValidator →
composite → подготовка/HTTP. Ошибки данных attributes/custom объединяются штатным
buildValidationFailure. Исключения конфигурации проходят buildExceptionResult.
CustomValidatableRequestInterface не зависит от Illuminate.

## Согласованные решения

### D1. Явный provider клиента имеет приоритет

**Принято:** в pipeline источник — ClientConfig текущего выполнения.
Если `containerProvider` явно задан, использовать только его `validatorFactory()`;
не обращаться к глобальному provider или Validator::useFactory при null/неправильном
типе результата. Отсутствующий валидатор обрабатывается по D2. Иначе явный
NullContainerProvider мог бы незаметно получить проверки другого контекста.

Если provider клиента не задан: сохранить `Validator::useFactory()` как явный
общий bootstrap, затем глобальный provider/auto-detect по действующему порядку.
Фабрику клиента не записывать в static factory или глобальный реестр, в том числе
временно на время запроса. Правило действует для sync, promise, повторных и
вложенных выполнений. Внутренний skipValidation не переопределять.

Пример: два клиента используют разную локализацию или собственные validation rules.
Клиент B должен применять собственные правила независимо от предыдущего запроса A.
Все сведения уже находятся в ClientConfig; новые обязательные настройки не нужны.

### D2. Объявленные проверки должны выполняться либо давать configuration_error

**Принято:** без `#[Validate]` не запрашивать фабрику вообще. Запросы без правил
и custom-only проверки сохраняют работу без Illuminate и контейнера.
При наличии правил и отсутствии совместимой фабрики — ConfigurationException
до custom/composite/сериализации/auth/HTTP, с понятным безопасным сообщением:
для `#[Validate]` недоступен валидатор; настройте фабрику через выбранный provider
или используйте существующий глобальный bootstrap, когда provider не задан.
Сообщение не должно советовать глобальный fallback для явно заданного provider.

В pipeline — `configuration_error`, пустой список validationErrors и отсутствие
HTTP-ответа. В raw/resolved ошибка доступна штатно; dataOrFail/throwOnErrors
выбрасывают исключение. В прямой валидации, включая isValid/errors, тоже исключение:
false/пустой список означали бы другое — проверенные данные, не отсутствие проверки.
Неподходящий объект фабрики также даёт configuration_error без содержимого объекта
или конкатенации произвольного сообщения из provider. Не превращать любые ошибки
пользовательских validation rules в новую общую ошибку конфигурации.

Невалидные данные при работающей фабрике по-прежнему дают `validation_failed`,
ValidationError с field/rule/message/input и ноль HTTP. Labels, validationMessages
и объединение attribute/custom ошибок сохраняются.

Zero-config: Laravel использует найденный валидатор автоматически. В standalone
обычный SDK без Laravel-правил не требует действий. Использование `#[Validate]`
требует реального движка Illuminate Validation, как и сейчас; установка компонента
сама по себе не означает, что настроены factory, пользовательские rules и сообщения.
Не создавать скрыто новую фабрику и не реализовывать урезанную копию Laravel rules
в ядре. Не добавлять обязательный флаг, strict-mode или новый `skip` по умолчанию.

Пример: SDK объявил обязательный email, но приложение не подключило валидатор.
Вместо отправки непроверенных данных вызывающий код получает ошибку настройки до HTTP.

### D3. Ручная валидация использует доступный контекст без привязки DTO к клиенту

**Принято:** ручные validate/isValid/errors у уже привязанного AbstractRequest
используют его клиентскую конфигурацию по D1. Не выполнять auto-resolve клиента
только ради валидации непривязанного запроса. Pipeline всегда использует контекст
фактического выполнения, даже если у объекта запроса оставлена другая привязка.

Самостоятельный DTO и непривязанный запрос сохраняют общий bootstrap/auto-detect.
Для явной проверки DTO с определённым provider добавить необязательный аргумент
к конкретным `Validator::check()` и `validateOrThrow()`:
`Validator::check($dto, provider: $provider)`. Он имеет приоритет над привязкой запроса
и общим bootstrap. Существующие вызовы с одним аргументом сохраняются;
ValidatorInterface и ValidatableInterface не должны требовать новых аргументов.
DTO не хранит ClientConfig/PipelineContext и не запоминает клиента последнего ответа.
Автоматическая валидация DTO после hydrate в эту поставку не входит.

Добавить `Validator::resetFactory()` для явного сброса legacy bootstrap и вызывать
его в общем тестовом reset. Не сбрасывать factory автоматически при создании клиента
или завершении запроса и не менять область ContainerProviderRegistry::reset.
Клиентские factory не требуют ручного reset между A/B: они не записываются глобально.

## Подход и шаги реализации

1. Перенести наблюдаемые сценарии V1–V4 и положительные controls в регрессионные
   тесты с именованными stubs. Проверять решение, выбранную factory и число HTTP.
   Наблюдение V5 закрепить тестом явного reset, сохранив bootstrap совместимость.
2. Отделить выбор источника factory от сбора атрибутов и выполнения правил.
   Переиспользовать текущий Validator и ContainerProviderInterface; не добавлять
   новый движок, mandatory config или второй контейнер. Сначала проверить наличие
   правил: отсутствие правил не требует даже вызова validatorFactory().
3. Передать в PipelineValidator фактическую конфигурацию выполнения. Внутренний
   вызов должен различать явно заданный provider и обычный bootstrap: нельзя
   заранее превратить null в NullContainerProvider и этим выключить useFactory.
   Точно так же нельзя выбирать клиента из запроса вместо PipelineContext.
4. Добавить безопасный выбор контекста для ручного bound request и необязательный
   provider для прямой проверки. Сохранить публичные однопараметровые вызовы,
   независимость DTO и уже существующий порядок attributes/custom/contracts.
5. Обработать недоступную/неправильную фабрику по D2; использовать существующее
   различие ConfigurationException и ValidationException. Не трогать redaction
   произвольных пользовательских сообщений, HTTP classification и auth retry.
6. Добавить отдельный reset static factory и подключить к TestTrait. Проверить
   вызовы тестов отдельно и в перемешанном порядке; прежние static настройки
   не должны неявно обеспечивать фабрикой следующие тесты.
7. Обновить публичные guides validation, container-provider, Laravel, errors и
   testing. Указать зависимости, приоритеты, isValid при недоступном движке,
   migration notes и отсутствие новых обязательных настроек. Обновить changelog
   без ссылок на workflow. Не объявлять весь AS-12 завершённым.
8. Выполнить матрицу ниже, targeted tests, полный composer test, strict-PSR при
   новых именованных типах и standalone smoke. Сохранить результаты и перевести
   весь комплект в completed после выполнения согласованного объёма.

## Матрица приёмки

| Область | Проверки |
| --- | --- |
| Приоритет | Client provider против global provider/useFactory; provider с null/неправильным объектом не использует fallback; без override legacy bootstrap и auto-detect работают. |
| Изоляция | Два клиента с разными правилами/сообщениями: A→B→A; создание нового клиента не меняет старого; вложенный вызов B из проверки A не оставляет глобального состояния. |
| Zero-config | Без правил factory не разрешается даже при сломанном provider. Обычный Laravel validator подхватывается автоматически. Standalone без Illuminate отправляет no-rules запрос. |
| Недоступность | Есть правила, factory отсутствует или несовместима: configuration_error, HTTP/auth/serialization/custom/composite не запускаются; никакой подстановки «успех». |
| Данные | Рабочая factory принимает/отклоняет правила; validation_failed отличается от configuration_error; labels, messages, input и объединение custom ошибок прежние. |
| Ручной вызов | Bound request следует своему клиенту; unbound request не запускает auto-resolve; DTO не связан с последним клиентом; явный provider работает в check/validateOrThrow; old signatures работают. |
| Доставка | raw/resolved/dataOrFail/throwOnErrors, sync/promise; ошибка конфигурации isValid/errors явно выбрасывается. |
| Порядок | Validation до composite и подготовки; custom-only без Illuminate; RequestContractValidator независимо; внутренний skipValidation сохраняется. |
| Состояние | Явный resetFactory, сохранение глобального bootstrap до reset, отсутствие записи scoped factory в глобальное состояние; тесты независимо от порядка. |
| Независимость | no-dev checkout без Illuminate; выбор factory через настоящий LaravelContainerProvider и фабрику с пользовательским правилом; текущая DTO hydration не меняется. |

После реализации дополнительно выполнить `composer test -- --order-by=random
--random-order-seed=20260912` для проверки взаимодействия с глобальными фабриками.
Зелёный прогон не доказывает отсутствие всех возможных shared-state проблем.

## Совместимость, ограничения и готовность

Согласованное изменение **не полностью обратно совместимо**. Если правила раньше молча
пропускались из-за отсутствующей фабрики, теперь выполнение остановится.
Пользователи isValid/errors должны отличать ConfigurationException от результата
проверки данных. Явный provider клиента получает приоритет над static/global
фабрикой; намеренный глобальный override такого клиента перестанет действовать.
Ошибки полей при доступном валидаторе и существующий standalone custom preflight
сохраняются. Новых обязательных полей ClientConfig и зависимостей ядра нет.

Вне объёма: полный AS-12 (HTTP request autofill, discovery, bindings транспорта,
config:cache, Octane), новый validation backend, автоматическая генерация правил
из PHP-типов, автоматическая проверка вложенных DTO после hydrate, новый error
protocol и реальный неблокирующий async. Удаление всего static API и общая
перестройка контейнера не требуются.

Подготовка завершена: V1–V4 воспроизведены, baseline и controls сохранены,
объём реализации и проверки описаны. D1–D3 согласованы пользователем 2026-09-12,
включая прекращение пропуска объявленных правил по D2. Реализация начата 2026-09-12.

## Результат реализации

D1–D3 выполнены 2026-09-12. Validator разделяет ручной вход `check()` и внутренний
`checkForClient()`: последний использует ClientConfig фактического выполнения,
не подменяя его привязкой request. Сбор правил и выполнение проверки общие.
При отсутствии правил factory не разрешается. Явный provider не использует общий
fallback; ошибка получения или несовместимый результат дают безопасную
ConfigurationException. Исключения самих validation rules не переименовываются.

Добавлены необязательный provider в check/validateOrThrow и явный resetFactory;
общий TestTrait очищает legacy factory. Поддержка ручных методов реализована
через общий Validator без изменения сигнатур ValidatableInterface/ValidatorInterface.
PHPDoc isValid уточняет ConfigurationException при недоступных проверках.
Удалены лишние setAccessible в затронутом Validator: они не нужны на PHP 8.4+
и создавали deprecation на PHP 8.5. Это объясняет сокращение числа предупреждений.

Публичные validation/container-provider/Laravel/errors/testing и changelog обновлены.
Пропуск объявленных проверок удалён как согласованная несовместимость;
ядро не получает новых обязательных зависимостей или настроек.

Проверки реализации:

| Проверка | Результат |
| --- | --- |
| Новые регрессионные сценарии до исправления | 12 failed, 11 assertions; приоритеты, отсутствующая factory и новые API не выполнялись. |
| ScopedValidationTest после реализации | 23 passed, 120 assertions; приоритеты, A→B→A, вложенная проверка, фактический executing client, ручные методы, null/неправильный/бросающий provider, no-rules, auto-detect, messages/custom, result/throwOnErrors. |
| `composer test` | 1323 passed, 34 deprecation, 4460 assertions, 6.25 s; падений нет. |
| Полный suite в случайном порядке, seed 20260912 | 1323 passed, 34 deprecation, 4460 assertions, 6.21 s; падений нет. |
| `composer dump-autoload --optimize --strict-psr` | Успех, включая новые test stubs. |
| Standalone validation | Успех в отдельной production-установке без Illuminate Container/Validation Factory: sync/promise, no-rules/custom, configuration_error при attributes, raw/resolved/dataOrFail и DTO validate/isValid/errors. |
| Standalone JSON/DTO | Прежние JSON, unwrap, точные ID, гидратация и доступность raw body сохранены. |
| PHP lint, локальные ссылки, `git diff --check` | Без ошибок. |

Воспроизведение приёмки из корня пакета:

```bash
vendor/bin/pest tests/Unit/Core/ScopedValidationTest.php
composer test
composer test -- --order-by=random --random-order-seed=20260912
php tests/Support/standalone-validation-smoke.php /path/to/no-dev-checkout
php tests/Support/standalone-json-smoke.php /path/to/no-dev-checkout
```

Новая проверка [standalone-validation-smoke](../../../tests/Support/standalone-validation-smoke.php)
содержит assertions текущего поведения. Исторические probe/baseline и SHA-256
в artifacts сохранены без изменения: воспроизведение baseline требует src коммита
6d5aed1. На новом коде старый probe может остановиться на теперь обязательном
исключении ручной валидации; для приёмки используются приведённые выше тесты.

Согласованный объём завершён. Полный AS-12 и перечисленные ограничения остаются
за пределами этой поставки.
