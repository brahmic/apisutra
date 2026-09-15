# Карта содержания и переносов

База — `cddd036`, 90 исходных Markdown-файлов. Эта карта назначает место каждому
файлу; точные соответствия разделов и якорей записаны в [moves.md](moves.md).
Покрытие API отдельно зафиксировано в [inventory.md](inventory.md).

Все пути в таблицах — относительно `docs/`; `../README.md` и `../CHANEGLOG.md`
обозначают корневые файлы; `../development/` — материалы разработчика ApiSutra.
Целевые пути реализованы, см. [дерево](structure.md) и [аудитории](audiences.md).
Каждый исходный файл встречается ровно один раз. Если путь исчезает, остаётся
страница перехода со старыми востребованными якорями без копии контракта.

## Индексы и образцы

| Сейчас | Цель | Действие |
| --- | --- | --- |
| `../CHANEGLOG.md` | `../CHANEGLOG.md` | Сохранить исторические записи; новые изменения ссылками на migration. |
| `../README.md` | `../README.md`, `start/README.md` | Краткое описание и прямой вход по задаче; код quickstart не копировать. |
| `README.md` | `README.md`, `start/README.md`, `reference/README.md` | Навигация по задачам и слоям; убрать второй подробный каталог. |
| `example/documentation-guide.md` | `guides/sdk/release.md`, `example/README.md` | Оформление и выпуск SDK; образец README рядом с исходниками. |
| `example/request-use-cases.md` | `guides/requests.md`, `example/README.md` | Практические сценарии связать с исполняемыми примерами. |
| `glossary.md` | `glossary/README.md` | Сохранить старый переход без дублирования индекса. |
| `guides/README.md` | `guides/README.md`, `start/README.md` | Развести индекс практики и выбор задачи. |

## Декларации и конфигурация

| Сейчас | Цель | Действие |
| --- | --- | --- |
| `guides/attributes/README.md` | `reference/attributes/README.md` | Декларации атрибутов; описание алгоритмов заменить ссылками на владельца. |
| `guides/attributes/behavior.md` | `reference/attributes/behavior.md` | Декларации атрибутов; описание алгоритмов заменить ссылками на владельца. |
| `guides/attributes/data-transfer.md` | `reference/attributes/hydration.md` | Декларации атрибутов; описание алгоритмов заменить ссылками на владельца. |
| `guides/attributes/hooks.md` | `reference/attributes/hooks.md` | Декларации атрибутов; описание алгоритмов заменить ссылками на владельца. |
| `guides/attributes/http.md` | `reference/attributes/http.md` | Декларации атрибутов; описание алгоритмов заменить ссылками на владельца. |
| `guides/attributes/request.md` | `reference/attributes/request.md` | Декларации атрибутов; описание алгоритмов заменить ссылками на владельца. |
| `guides/attributes/response.md` | `reference/attributes/response.md` | Декларации атрибутов; описание алгоритмов заменить ссылками на владельца. |
| `guides/client-config/README.md` | `reference/client/configuration.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/archive.md` | `reference/files/archives.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/auth.md` | `reference/auth/strategies.md`, `reference/auth/tokens.md`, `reference/auth/credentials.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/cache.md` | `reference/execution/cache.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/container-provider.md` | `reference/client/construction.md`, `reference/client/validation.md`, `reference/integrations/laravel.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/extensions.md` | `reference/extensions/extensions.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/observability.md` | `reference/results/observability.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/pagination.md` | `reference/execution/pagination.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/pool.md` | `reference/execution/batch-pool.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/rate-limit.md` | `reference/execution/rate-limit.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/responses-errors.md` | `reference/results/handles.md`, `reference/results/errors.md`, `reference/execution/continuation-state.md`, `reference/execution/continuation-await.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/retry.md` | `reference/execution/retry.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/serialization.md` | `reference/serialization/request-parts.md`, `reference/serialization/body.md`, `reference/serialization/dto-output.md`, `reference/dto/profiles.md`, `reference/dto/field-rules.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |
| `guides/client-config/timeouts-delay.md` | `reference/execution/deadlines.md` | Параметры и defaults — у тематического владельца; каталог ClientConfig ведёт туда. |

## Практические руководства и тематические контракты

| Сейчас | Цель | Действие |
| --- | --- | --- |
| `guides/auth.md` | `reference/auth/strategies.md`, `reference/auth/tokens.md`, `reference/auth/credentials.md` | Разделить способы auth, refresh/token cache и размещение credentials; исключить дубли config. |
| `guides/batch.md` | `reference/execution/batch-pool.md` | Совместить batch/pool-контракт с настройками pool; не обещать неблокирующий I/O. |
| `guides/casts.md` | `reference/serialization/casts.md`, `reference/dto/scope.md`, `reference/dto/lifecycle.md` | Источники casts отдельно от scope и изоляции аргументов. |
| `guides/client-discovery.md` | `reference/client/discovery.md` | Один справочник discovery/resolution; Laravel binding — отдельная интеграция. |
| `guides/collections.md` | `reference/dto/collections.md` | Контракт коллекций; правила list/DTO связать с shapes. |
| `guides/continuation-token.md` | `reference/execution/continuation-await.md`, `reference/results/handles.md` | Extractor/token-only входы; ссылки на Ready/Pending вместо старых эвристик. |
| `guides/dto.md` | `guides/dto/attribute-models.md`, `reference/dto/models.md`, `reference/dto/profiles.md`, `reference/dto/scalars.md`, `reference/dto/defaults.md`, `reference/dto/shapes.md`, `reference/dto/collections.md`, `reference/dto/lifecycle.md`, `reference/serialization/dto-output.md` | Оставить атрибутный рецепт; перенести самостоятельные контракты по теме. |
| `guides/errors.md` | `reference/results/handles.md`, `reference/results/errors.md`, `reference/dto/diagnostics.md`, `reference/execution/continuation-await.md` | Result API, классификация, sourcePath и await имеют разных владельцев. |
| `guides/extensions.md` | `guides/recipes/extensions.md`, `reference/extensions/extensions.md` | Рецепт подключения отдельно от контрактов и lifecycle. |
| `guides/external-urls.md` | `reference/serialization/uri-query.md`, `reference/auth/credentials.md` | Готовые URL и origin; изоляция credentials у auth-владельца. |
| `guides/files.md` | `guides/recipes/files.md`, `reference/files/uploads.md`, `reference/files/downloads.md`, `reference/files/archives.md` | Разделить владение upload/download потоками и архивы. |
| `guides/hooks.md` | `reference/extensions/hooks.md` | Порядок, контекст и границы sourcePath; параметры атрибутов по ссылке. |
| `guides/hydration-rules.md` | `guides/dto/plain-models.md`, `reference/dto/field-rules.md`, `reference/dto/profiles.md`, `reference/dto/scalars.md`, `reference/dto/defaults.md`, `reference/dto/shapes.md`, `reference/dto/variants.md`, `reference/dto/extras.md`, `reference/dto/scope.md`, `reference/dto/diagnostics.md`, `reference/serialization/receiver-output.md` | Контракты 028 не переизобретать; переносить с тестами опубликованного примера. |
| `guides/laravel.md` | `guides/integration/laravel.md`, `reference/integrations/laravel.md` | Путь подключения отдельно от bindings, discovery и совместимости. |
| `guides/live-testing.md` | `guides/testing/live.md`, `reference/testing/live.md` | Provider-практика отдельно от существующих core helpers; live I/O не обязателен. |
| `guides/logging.md` | `reference/results/observability.md` | Единый источник logging/debug/redaction; sourcePath по ссылке. |
| `guides/megaclient.md` | `guides/integration/multi-service.md`, `reference/client/construction.md`, `reference/client/resources.md` | Выбор структуры и ресурсные контракты; не навязывать мегаклиент простому SDK. |
| `guides/migration.md` | `migration/README.md`, `migration/v0.2.0-alpha.1.md`, `migration/unreleased.md` | Версионные действия отдельно от текущих полных правил; сохранить старые якоря. |
| `guides/naming-strategy.md` | `reference/serialization/request-parts.md`, `reference/serialization/dto-output.md`, `reference/dto/profiles.md` | Стратегия по направлению; короткий старый указатель вместо четвёртой спецификации. |
| `guides/operation-inventory.md` | `reference/client/operation-inventory.md`, `reference/client/catalogs.md`, `reference/client/response-dto-catalog.md`, `guides/sdk/coverage.md` | Контракты inventory/catalog отдельно от работы с покрытием SDK. |
| `guides/pagination.md` | `guides/recipes/pagination.md`, `reference/execution/pagination.md` | Рецепт и полный контракт; items-only с rules и без описать точно. |
| `guides/provider-analysis.md` | `guides/sdk/analysis.md` | Сбор проверенных фактов API и неизвестных условий; без порядка всей разработки. |
| `guides/provider-async-await.md` | `guides/recipes/continuation.md`, `reference/execution/continuation-state.md`, `reference/execution/continuation-await.md` | Рецепт отдельно от 030-контрактов; код resolver переиспользовать из примера. |
| `guides/provider-catalogs.md` | `reference/client/catalogs.md`, `reference/client/response-dto-catalog.md`, `guides/sdk/coverage.md` | Read-only catalogs, metadata и runtime meta не смешивать. |
| `guides/provider-checklist.md` | `guides/sdk/coverage.md`, `guides/sdk/release.md` | Проверка результатов по ссылкам; не повторять порядок и объяснения методологии. |
| `guides/provider-methodology.md` | `start/create-sdk.md`, `guides/sdk/design.md`, `guides/sdk/first-operation.md`, `guides/sdk/coverage.md`, `guides/sdk/release.md`, `guides/integration/multi-service.md`, `guides/testing/unit.md` | Главный порядок — в start; структура SDK — design; остальные правила у владельцев. |
| `guides/quickstart.md` | `guides/quickstart.md`, `guides/integration/standalone.md`, `guides/integration/laravel.md` | Сохранить URL и runnable smoke; явная сборка по умолчанию. |
| `guides/redis-rate-limit.md` | `reference/integrations/redis.md`, `reference/execution/rate-limit.md` | Redis installation/зависимости отдельно от семантики квот. |
| `guides/request-pipeline.md` | `reference/request/composition.md`, `reference/extensions/hooks.md` | Composite/DependsOn отдельно от callback/hook contracts. |
| `guides/requests.md` | `guides/requests.md`, `reference/request/declaration.md`, `reference/serialization/request-parts.md` | Практическое описание одной операции; convention/runtime API в reference. |
| `guides/resources.md` | `reference/client/resources.md`, `guides/sdk/design.md` | Ресурсный API и владение типами; без копии целого SDK layout. |
| `guides/retries-rate-limit.md` | `reference/execution/retry.md`, `reference/execution/rate-limit.md`, `reference/execution/deadlines.md` | Разделить retry, квоты и общий бюджет; связать условия применения. |
| `guides/serialization.md` | `reference/serialization/request-parts.md`, `reference/serialization/uri-query.md`, `reference/serialization/body.md`, `reference/serialization/dto-output.md`, `reference/serialization/receiver-output.md`, `reference/dto/scalars.md` | Request parts, wire, DX и большие целые имеют самостоятельные контракты. |
| `guides/testing.md` | `guides/testing/unit.md`, `reference/testing/mocking.md`, `reference/testing/fixtures.md`, `reference/testing/live.md`, `../development/testing.md` | Практика отдельно от fake/assert/recording API; документационные smoke не копируют код. |
| `guides/transport.md` | `reference/execution/transport.md`, `reference/execution/deadlines.md` | Фактический sync/promise путь, зависимости, timeout/deadline и replay. |
| `guides/troubleshooting.md` | `guides/troubleshooting.md`, `start/diagnose.md` | Симптом → диагностика → прямой контракт; не новая классификация ошибок. |
| `guides/use-cases.md` | `start/README.md`, `guides/README.md`, `guides/recipes/pagination.md`, `guides/recipes/continuation.md`, `guides/recipes/files.md` | Убрать параллельный каталог задач; содержательные рецепты сохранить. |
| `guides/validation.md` | `reference/client/validation.md`, `guides/testing/unit.md` | Зависимость от фабрики, клиентский контекст, standalone и кастомная проверка. |
| `guides/versioning.md` | `reference/client/versioning.md`, `guides/integration/multi-service.md` | Контракт explicit/default версии и небольшой сценарий использования. |

## Пользовательские определения и отдельные материалы разработчика

| Сейчас | Цель | Действие |
| --- | --- | --- |
| `glossary/README.md` | `glossary/README.md` | Сократить до тематических групп и поиска сущностей; не копировать всё оглавление. |
| `glossary/architecture.md` | `glossary/architecture.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/attributes.md` | `glossary/attributes.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/auth.md` | `glossary/auth.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/client.md` | `glossary/client.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/collections.md` | `glossary/collections.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/dto.md` | `glossary/dto.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/execution.md` | `glossary/execution.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/extensions.md` | `glossary/extensions.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/files.md` | `glossary/files.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/laravel.md` | `glossary/laravel.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/pagination.md` | `glossary/pagination.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/pipeline.md` | `glossary/pipeline.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/requests.md` | `glossary/requests.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/results.md` | `glossary/results.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `glossary/testing.md` | `glossary/testing.md` | Оставить определения пользователя; внутренние помощники — development, устаревшие символы удалить. |
| `technical/README.md` | `../development/README.md`, `reference/README.md` | Внутреннее устройство — development, пользовательские гарантии — reference; старый адрес остаётся переходом. |
| `technical/architecture.md` | `../development/architecture.md`, `reference/client/construction.md` | Внутреннее устройство — development, пользовательские гарантии — reference; старый адрес остаётся переходом. |
| `technical/attributes.md` | `../development/attributes.md`, `reference/attributes/README.md`, `reference/extensions/extensions.md` | Внутреннее устройство — development, пользовательские гарантии — reference; старый адрес остаётся переходом. |
| `technical/caching-retry.md` | `../development/caching-retry.md`, `reference/execution/cache.md`, `reference/execution/retry.md` | Внутреннее устройство — development, пользовательские гарантии — reference; старый адрес остаётся переходом. |
| `technical/error-handling.md` | `../development/error-handling.md`, `reference/results/errors.md` | Внутреннее устройство — development, пользовательские гарантии — reference; старый адрес остаётся переходом. |
| `technical/execution.md` | `../development/execution.md`, `reference/execution/transport.md`, `reference/execution/continuation-await.md` | Внутреннее устройство — development, пользовательские гарантии — reference; старый адрес остаётся переходом. |
| `technical/pipeline.md` | `../development/pipeline.md`, `reference/request/declaration.md`, `reference/extensions/hooks.md` | Внутреннее устройство — development, пользовательские гарантии — reference; старый адрес остаётся переходом. |

## Как не потерять раздел при разбиении

При смешении аудиторий разделы классифицируются отдельно. `docs/glossary/architecture.md`
описывает устройство пользовательского SDK и остаётся публичным. Внутренний pipeline
ядра относится к development, а контракты hooks/casts/transports — к публичному reference.
CONTRIBUTING и новые dev-руководства не входят в 90 исходных файлов: это новые входы;
их содержание не должно дублировать инструкции `.agents/` и публичные контракты.

Для каждого заголовка крупного исходника перед удалением указать одно из действий:
перенесён целиком; объединён с конкретным разделом-владельцем; пример вынесен в PHP;
сокращён до ссылки; устарел с проверенным основанием. Нельзя отмечать «готово» только
потому, что создана хотя бы одна страница из столбца «Цель».

Крупные исходники раскладываются так: 700 строк методологии — по этапам SDK, 351 строка внешних
правил по контрактам DTO. Аналогично auth делится на стратегии и token lifecycle,
сериализация — на request parts/URI/wire/DX, errors — на result API/классификацию.
Остальные страницы становятся компактнее за счёт удаления повторов, а не за счёт
переноса важных ограничений в необязательные примечания.

Перед каждой порцией карта сверяется с новым `src/` и тестами. Изменение одного
назначения обновляет дерево и входящие ссылки той же порцией. Имена старых публичных
файлов сохраняются переходами; исторические артефакты остаются неизменными.
