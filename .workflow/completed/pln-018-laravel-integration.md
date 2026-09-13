# Предсказуемая Laravel-интеграция без обязательной настройки ядра

- Дата создания: 2026-09-12
- Дата обновления: 2026-09-13
- Статус: завершён

## Основание и цель

[Аудит L1–L3](../audit/aud-003-remaining-work/aud-003-readme.md), AS-12 и F13.
[SdkServiceProvider](../../src/Laravel/SdkServiceProvider.php) уже существует;
переписывать discovery/resolver и создавать ещё один контейнер не требуется.

Цель — воспроизводимое подключение в Laravel 12, сохранение пользовательских bindings
и отсутствие неявной подмены данных SDK-запроса входящими HTTP-параметрами.

Воспроизведено: DI-запрос с query=`explicit` получает `incoming` из Http Request;
без HTTP-контекста query становится null. Provider заменяет ранее заданные фабрику
и ClientRegistry. Пользовательский TransportInterface уже защищён bound-проверкой.
Полный Laravel boot/config:cache/worker пока не проверен; это задача реализации,
не доказанный отказ этих режимов.

## Согласованный контракт

В2 принят пользователем 2026-09-12 после продуктового объяснения — О2 в
[аудите](../audit/aud-003-remaining-work/aud-003-readme.md#ответы).
Открытых продуктовых вопросов нет; реализация ещё не начата.

- Добавить package discovery через extra.laravel.providers после устранения побочных
  эффектов provider. Установка в Laravel подключает безопасные bindings автоматически;
  ручная регистрация остаётся допустимой. В приложении без Laravel ничего не запускается.
- Обычный resolve AbstractRequest привязывает клиента, если он ещё не установлен,
  и сохраняет созданный объект/явные значения. Входящий Http Request не читается.
- Перенос входящих параметров выполнять явным вызовом уже существующей
  RequestFactoryInterface::make(RequestClass::class, $httpRequest или $payload).
  Не вводить обязательный ClientConfig-флаг и новый атрибут ради обычного DI.
- Существующие пользовательские bindings имеют приоритет над defaults пакета;
  регистрация defaults не должна пересоздавать уже разрешённые пользовательские объекты.

Пример: Artisan создаёт запрос с id=15 — id остаётся 15. Контроллер, которому нужен
перенос query/body/route, вызывает фабрику явно; остальные SDK-запросы не зависят
от входящего HTTP-контекста. Это меняет документированное прежнее auto-fill поведение
и требует migration-примера.

## Zero-config и зависимости

Не переносить Illuminate в обязательные зависимости standalone ядра. Laravel-приложение
предоставляет свой framework, Guzzle/PSR-18 клиент; собственный транспорт можно передать,
как сейчас. Не требовать Redis, БД, публикации конфига и настройки префиксов.
Не создавать глобальную конфигурацию с готовыми ClientConfig/auth/cache-объектами:
в config сохраняются скаляры, сборка объектов происходит в контейнере.

Для проверки нужен отдельный тестовый Laravel 12 application в tests/Integration/Laravel
с собственным composer.json и lock. Это тестовое окружение, не production-зависимость
потребителя. Предпочесть реальный минимальный bootstrap Laravel с пакетом через path
repository; установка должна не подтягивать require-dev самого ApiSutra. HTTP заменить
тестовым PSR/SDK-транспортом, queue использовать sync/контролируемый worker без БД.
Не обещать поддержку Laravel 13 или Octane по результатам проверки Laravel 12.

Механизм [package discovery и отказ от него](https://laravel.com/docs/12.x/packages#package-discovery)
и ограничения [config:cache](https://laravel.com/docs/12.x/packages#configuration)
проверены по документации Laravel 12. Целевая версия выбрана по issue/текущим зависимостям,
а не как утверждение о последней версии Laravel.

## Подход и этапы

1. Добавить regression-тесты L1/L2: сохранить явные mutable/readonly значения,
   установленного клиента, runtime-опции и экземпляр factory/registry/resolver,
   зарегистрированный до provider. Проверить регистрацию пользовательских bindings
   после provider и повторный bootstrap без дублирования callbacks.
2. Убрать глобальное копирование состояния из resolving callback. Сохранить явную
   RequestFactory, её разделение route/query/body/header/files и ограничения readonly.
   Проверить, что запросы с обязательными constructor-параметрами используют обычный
   Laravel binding/явную фабрику, без скрытого заполнения недостающих значений.
3. Применить условную регистрацию defaults к расширяемым bindings. Не заменять
   пользовательские TransportInterface, PSR client/factories, RequestFactoryInterface,
   ClientResponseAdapterInterface, ClientResolverInterface, ClientRegistry и discovery
   зависимости. Ошибка существующего binding должна оставаться диагностируемой;
   не маскировать её переключением на стандартный адаптер.
4. После этого добавить discovery metadata и точный quickstart: zero-config bootstrap,
   ручная регистрация при отключённом discovery, binding конкретного SDK-клиента,
   явный factory для входящих данных и подмена SDK-транспорта. Не обещать, что
   Laravel Http::fake перехватывает прямой Guzzle/PSR-18 транспорт.
5. Собрать минимальное Laravel-приложение и проверить HTTP controller, Artisan,
   job/два последовательных задания в одном процессе, config:cache и повторный boot.
   Два клиента с разными namespace/config и ранее установленный request client
   должны выбираться однозначно. Не регистрировать динамический tenant-клиент
   глобальным singleton; фактический жизненный цикл задаёт приложение.
6. Если lifecycle-тест обнаружит течь в registry/resolver, исправить её внутри
   существующих границ с regression-тестом. Не вводить автоматическую tenant identity
   и новый протокол tenant lifecycle. Octane отдельно отметить как непроверенный.
7. Проверить standalone --no-dev smoke: явный транспорт работает, отсутствие
   Laravel не мешает загрузке ядра; специфические возможности требуют зависимостей
   только при вызове. Проверка Validate уже реализована и не переделывается.
8. Запустить Unit/Laravel, Core/RequestFactory, Resolver, Auth/TokenIsolation,
   Execution и полный composer test; точные каталоги сверить при реализации.
   Подключение integration job к общей матрице координировать с [pln-020](pln-020-release-readiness.md).

## Совместимость и документация

Auto-fill при DI перестанет происходить; необходим пример миграции на существующую
явную фабрику. Пользовательские bindings начнут сохраняться. Discovery начнёт
регистрировать provider автоматически; учесть приложения, регистрирующие его вручную.
Публичные auth/logger/cache настройки и приоритеты конкретного клиента не меняются.

Обновить Laravel/quickstart/container/testing/troubleshooting guides, проверяемые
примеры и CHANEGLOG.md. Не переносить правила отдельного provider SDK в ядро.

Критерии завершения: В2 согласован, L1/L2 исправлены, bootstrap и три контекста
исполнения проверены в настоящем Laravel 12, config:cache проходит, пользовательские
bindings сохраняются, standalone остаётся работоспособным. Один зелёный Container-test
не считается выполнением всей Laravel-приёмки. Реализация ещё не начата.


## Проверка плана — 2026-09-12

Повторены L1/L2. После проверки В2 согласован пользователем (О2 аудита), включая
миграцию контроллеров, полагавшихся на документированное auto-fill, на явную фабрику.
Обычный SDK DI сохраняет заданные значения; RequestFactory остаётся доступной
для намеренного переноса входящих данных. Package discovery добавлять только
после устранения автоматического копирования HTTP-данных и перезаписи bindings.
Готовность настоящего Laravel-приложения/config:cache этой проверкой не утверждается.


## Реализация и приёмка — 2026-09-12

Убрано глобальное заполнение request из HTTP. Default bindings регистрируются
условно; повторная регистрация provider не дублирует callbacks. Реальный bootstrap
выявил дополнительный TypeError при разрешении строкового env: resolving callbacks
ограничены AbstractRequest/MultiServiceClientInterface. Некорректные явные PSR bindings
не маскируются fallback. Добавлено package discovery metadata.

[Laravel приложение](../../tests/Integration/Laravel/README.md) использует собственный lock
и path-пакет без require-dev ядра. Его vendor находится в исключённой из Git
`.laravel-integration/vendor` вне tests: иначе поиск datasets Pest обходит циклическую
ссылку path-пакета. Основные integration-тесты сохранены в общей PHPUnit suite.

Проверены HTTP, явная фабрика, readonly/обязательный конструктор, bindings до/после
provider, существующий клиент, два namespace, Artisan, sync dispatch двух заданий
в одном процессе, повторный bootstrap и config:cache. Проверка job lifecycle не
объявляется проверкой Octane или внешнего queue worker. Требования БД/Redis не добавлены.

`vendor/bin/pest --compact`: 1443 passed, 33 прежних deprecation, 5268 assertions.
`php tests/Integration/Laravel/verify.php`, `composer validate --strict` для обоих
проектов и standalone auth в отдельной --no-dev копии прошли. Guides/changelog обновлены.
