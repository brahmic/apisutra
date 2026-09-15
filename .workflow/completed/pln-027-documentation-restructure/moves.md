# Переносы разделов и совместимость адресов

База реализации — `6a649f5e247adba3ecc0c82f1ba68cb74c018aba`. Учтены все 90
исходных файлов и 1240 заголовков, включая вложенные. Таблица ниже — файловый
указатель, а [полная карта разделов](artifacts/move-check.json) содержит для каждого
исходную строку, заголовок, прежний якорь, новый файл/якорь, действие, прежние входящие
ссылки и результат проверки. Это детализация `moves.md`, а не отдельная спецификация.

[check-moves.py](artifacts/check-moves.py) повторно читает принятый commit, сравнивает
полную последовательность заголовков и проверяет оба конца переноса. Отсутствующие
прежние входящие ссылки означают только отсутствие ссылок внутри baseline; внешние
ссылки всё равно защищены сохранением адреса. [Проверка документации](artifacts/documentation-check.json)
контролирует уже текущие ссылки и достижимость владельцев.

## Решения при переносе

- 67 прежних файлов стали переходами с HTML id и прямыми ссылками; полной старой
  спецификации в них нет. На оставшихся прежних URL сохранены необходимые якоря.
- Guides объясняют применение, glossary — термины; полное поведение перенесено
  в reference. Атрибутные таблицы сохраняют декларации и ссылаются на алгоритмы.
- Внутреннее technical перенесено в development. Старый публичный адрес ведёт
  к URL репозитория, поскольку development не входит в dist.
- Старые примеры методологии переработаны по назначению: выбор слоя — проектирование,
  sandbox — live-тестирование, result metadata — результаты, continuation — готовность.
  Исполняемый Records SDK заменяет неполные quickstart и образцы несуществующих SDK.
- Подробный каталог DTO ответа отделён от статических каталогов; добавлены два
  самостоятельных запуска examples. Обоснование трёх дополнительных страниц — в structure.md.

Начальная карта переноса [section-moves.json](artifacts/section-moves.json) была
составлена при разбиении текста. Она сохранена как промежуточный артефакт, не как
доказательство итоговых владельцев. После редакционной сверки итогом стали
[resolved-section-moves.json](artifacts/resolved-section-moves.json) и её проверка.
Человекочитаемый указатель `moves.md` собран при приёмке, а не до первого переноса:
это отклонение от последовательности плана; предварительный учёт выполняла машинная карта.

## Указатель исходных файлов

Во всех строках адреса проверены. Смысл переноса дополнительно сверялся по темам
[inventory](inventory.md) и пользовательским маршрутам [route-review](route-review.md).
Проверка ссылок сама по себе не доказывает семантическую эквивалентность.

| Исходник | Разделов | Новые владельцы |
| --- | ---: | --- |
| `CHANEGLOG.md` | 25 | [CHANEGLOG.md](../../../CHANEGLOG.md) |
| `README.md` | 9 | [README.md](../../../README.md), [development/testing.md](../../../development/testing.md), [guides/quickstart.md](../../../docs/guides/quickstart.md) |
| `docs/README.md` | 9 | [README.md](../../../docs/README.md) |
| `docs/example/documentation-guide.md` | 34 | [guides/sdk/release.md](../../../docs/guides/sdk/release.md) |
| `docs/example/request-use-cases.md` | 43 | [guides/recipes/files.md](../../../docs/guides/recipes/files.md), [guides/recipes/pagination.md](../../../docs/guides/recipes/pagination.md), [reference/execution/transport.md](../../../docs/reference/execution/transport.md), [reference/integrations/laravel.md](../../../docs/reference/integrations/laravel.md), [reference/results/errors.md](../../../docs/reference/results/errors.md), [reference/results/handles.md](../../../docs/reference/results/handles.md) |
| `docs/glossary.md` | 1 | [glossary/README.md](../../../docs/glossary/README.md) |
| `docs/glossary/README.md` | 17 | [glossary/README.md](../../../docs/glossary/README.md) |
| `docs/glossary/architecture.md` | 19 | [glossary/architecture.md](../../../docs/glossary/architecture.md), [reference/client/response-dto-catalog.md](../../../docs/reference/client/response-dto-catalog.md) |
| `docs/glossary/attributes.md` | 22 | [glossary/attributes.md](../../../docs/glossary/attributes.md) |
| `docs/glossary/auth.md` | 14 | [glossary/auth.md](../../../docs/glossary/auth.md) |
| `docs/glossary/client.md` | 38 | [glossary/client.md](../../../docs/glossary/client.md) |
| `docs/glossary/collections.md` | 10 | [glossary/collections.md](../../../docs/glossary/collections.md) |
| `docs/glossary/dto.md` | 47 | [glossary/dto.md](../../../docs/glossary/dto.md) |
| `docs/glossary/execution.md` | 34 | [glossary/execution.md](../../../docs/glossary/execution.md) |
| `docs/glossary/extensions.md` | 11 | [glossary/extensions.md](../../../docs/glossary/extensions.md) |
| `docs/glossary/files.md` | 8 | [glossary/files.md](../../../docs/glossary/files.md) |
| `docs/glossary/laravel.md` | 9 | [glossary/laravel.md](../../../docs/glossary/laravel.md) |
| `docs/glossary/pagination.md` | 13 | [glossary/pagination.md](../../../docs/glossary/pagination.md) |
| `docs/glossary/pipeline.md` | 23 | [glossary/pipeline.md](../../../docs/glossary/pipeline.md) |
| `docs/glossary/requests.md` | 17 | [glossary/requests.md](../../../docs/glossary/requests.md) |
| `docs/glossary/results.md` | 66 | [glossary/results.md](../../../docs/glossary/results.md) |
| `docs/glossary/testing.md` | 17 | [glossary/testing.md](../../../docs/glossary/testing.md) |
| `docs/guides/README.md` | 7 | [guides/README.md](../../../docs/guides/README.md) |
| `docs/guides/attributes/README.md` | 3 | [reference/attributes/README.md](../../../docs/reference/attributes/README.md) |
| `docs/guides/attributes/behavior.md` | 10 | [reference/attributes/behavior.md](../../../docs/reference/attributes/behavior.md) |
| `docs/guides/attributes/data-transfer.md` | 18 | [reference/attributes/hydration.md](../../../docs/reference/attributes/hydration.md), [reference/dto/collections.md](../../../docs/reference/dto/collections.md), [reference/dto/defaults.md](../../../docs/reference/dto/defaults.md) |
| `docs/guides/attributes/hooks.md` | 6 | [reference/attributes/hooks.md](../../../docs/reference/attributes/hooks.md) |
| `docs/guides/attributes/http.md` | 8 | [reference/attributes/http.md](../../../docs/reference/attributes/http.md) |
| `docs/guides/attributes/request.md` | 15 | [reference/attributes/request.md](../../../docs/reference/attributes/request.md) |
| `docs/guides/attributes/response.md` | 7 | [reference/attributes/response.md](../../../docs/reference/attributes/response.md) |
| `docs/guides/auth.md` | 25 | [reference/auth/README.md](../../../docs/reference/auth/README.md), [reference/auth/credentials.md](../../../docs/reference/auth/credentials.md), [reference/auth/strategies.md](../../../docs/reference/auth/strategies.md), [reference/auth/tokens.md](../../../docs/reference/auth/tokens.md) |
| `docs/guides/batch.md` | 10 | [reference/execution/batch-pool.md](../../../docs/reference/execution/batch-pool.md) |
| `docs/guides/casts.md` | 13 | [reference/dto/lifecycle.md](../../../docs/reference/dto/lifecycle.md), [reference/serialization/casts.md](../../../docs/reference/serialization/casts.md) |
| `docs/guides/client-config/README.md` | 3 | [glossary/client.md](../../../docs/glossary/client.md), [reference/client/configuration.md](../../../docs/reference/client/configuration.md) |
| `docs/guides/client-config/archive.md` | 5 | [reference/files/archives.md](../../../docs/reference/files/archives.md) |
| `docs/guides/client-config/auth.md` | 9 | [reference/auth/credentials.md](../../../docs/reference/auth/credentials.md), [reference/auth/strategies.md](../../../docs/reference/auth/strategies.md), [reference/auth/tokens.md](../../../docs/reference/auth/tokens.md), [reference/execution/batch-pool.md](../../../docs/reference/execution/batch-pool.md) |
| `docs/guides/client-config/cache.md` | 9 | [reference/attributes/behavior.md](../../../docs/reference/attributes/behavior.md), [reference/execution/cache.md](../../../docs/reference/execution/cache.md) |
| `docs/guides/client-config/container-provider.md` | 6 | [reference/client/construction.md](../../../docs/reference/client/construction.md) |
| `docs/guides/client-config/extensions.md` | 4 | [glossary/extensions.md](../../../docs/glossary/extensions.md), [reference/extensions/extensions.md](../../../docs/reference/extensions/extensions.md) |
| `docs/guides/client-config/observability.md` | 5 | [reference/results/observability.md](../../../docs/reference/results/observability.md) |
| `docs/guides/client-config/pagination.md` | 7 | [guides/recipes/pagination.md](../../../docs/guides/recipes/pagination.md), [reference/attributes/behavior.md](../../../docs/reference/attributes/behavior.md), [reference/execution/pagination.md](../../../docs/reference/execution/pagination.md) |
| `docs/guides/client-config/pool.md` | 8 | [reference/execution/batch-pool.md](../../../docs/reference/execution/batch-pool.md) |
| `docs/guides/client-config/rate-limit.md` | 7 | [reference/execution/rate-limit.md](../../../docs/reference/execution/rate-limit.md) |
| `docs/guides/client-config/responses-errors.md` | 10 | [reference/execution/continuation-await.md](../../../docs/reference/execution/continuation-await.md), [reference/results/errors.md](../../../docs/reference/results/errors.md), [reference/results/handles.md](../../../docs/reference/results/handles.md) |
| `docs/guides/client-config/retry.md` | 5 | [reference/attributes/behavior.md](../../../docs/reference/attributes/behavior.md), [reference/execution/retry.md](../../../docs/reference/execution/retry.md) |
| `docs/guides/client-config/serialization.md` | 10 | [reference/dto/field-rules.md](../../../docs/reference/dto/field-rules.md), [reference/serialization/body.md](../../../docs/reference/serialization/body.md), [reference/serialization/casts.md](../../../docs/reference/serialization/casts.md), [reference/serialization/dto-output.md](../../../docs/reference/serialization/dto-output.md), [reference/serialization/request-parts.md](../../../docs/reference/serialization/request-parts.md) |
| `docs/guides/client-config/timeouts-delay.md` | 6 | [reference/execution/deadlines.md](../../../docs/reference/execution/deadlines.md) |
| `docs/guides/client-discovery.md` | 10 | [reference/client/discovery.md](../../../docs/reference/client/discovery.md) |
| `docs/guides/collections.md` | 11 | [reference/dto/collections.md](../../../docs/reference/dto/collections.md) |
| `docs/guides/continuation-token.md` | 5 | [reference/execution/continuation-await.md](../../../docs/reference/execution/continuation-await.md) |
| `docs/guides/dto.md` | 21 | [glossary/dto.md](../../../docs/glossary/dto.md), [reference/client/validation.md](../../../docs/reference/client/validation.md), [reference/dto/collections.md](../../../docs/reference/dto/collections.md), [reference/dto/defaults.md](../../../docs/reference/dto/defaults.md), [reference/dto/lifecycle.md](../../../docs/reference/dto/lifecycle.md), [reference/dto/models.md](../../../docs/reference/dto/models.md), [reference/dto/profiles.md](../../../docs/reference/dto/profiles.md), [reference/dto/scalars.md](../../../docs/reference/dto/scalars.md), [reference/dto/shapes.md](../../../docs/reference/dto/shapes.md), [reference/serialization/dto-output.md](../../../docs/reference/serialization/dto-output.md) |
| `docs/guides/errors.md` | 23 | [reference/dto/diagnostics.md](../../../docs/reference/dto/diagnostics.md), [reference/execution/continuation-await.md](../../../docs/reference/execution/continuation-await.md), [reference/results/errors.md](../../../docs/reference/results/errors.md), [reference/results/handles.md](../../../docs/reference/results/handles.md) |
| `docs/guides/extensions.md` | 7 | [guides/recipes/extensions.md](../../../docs/guides/recipes/extensions.md), [reference/extensions/extensions.md](../../../docs/reference/extensions/extensions.md) |
| `docs/guides/external-urls.md` | 5 | [reference/auth/credentials.md](../../../docs/reference/auth/credentials.md), [reference/serialization/uri-query.md](../../../docs/reference/serialization/uri-query.md) |
| `docs/guides/files.md` | 15 | [glossary/files.md](../../../docs/glossary/files.md), [reference/files/archives.md](../../../docs/reference/files/archives.md), [reference/files/downloads.md](../../../docs/reference/files/downloads.md), [reference/files/uploads.md](../../../docs/reference/files/uploads.md) |
| `docs/guides/hooks.md` | 7 | [reference/extensions/hooks.md](../../../docs/reference/extensions/hooks.md) |
| `docs/guides/hydration-rules.md` | 11 | [guides/dto/plain-models.md](../../../docs/guides/dto/plain-models.md), [reference/dto/diagnostics.md](../../../docs/reference/dto/diagnostics.md), [reference/dto/extras.md](../../../docs/reference/dto/extras.md), [reference/dto/field-rules.md](../../../docs/reference/dto/field-rules.md), [reference/dto/scalars.md](../../../docs/reference/dto/scalars.md), [reference/dto/scope.md](../../../docs/reference/dto/scope.md), [reference/dto/shapes.md](../../../docs/reference/dto/shapes.md), [reference/dto/variants.md](../../../docs/reference/dto/variants.md), [reference/serialization/receiver-output.md](../../../docs/reference/serialization/receiver-output.md) |
| `docs/guides/laravel.md` | 15 | [guides/integration/multi-service.md](../../../docs/guides/integration/multi-service.md), [reference/integrations/laravel.md](../../../docs/reference/integrations/laravel.md) |
| `docs/guides/live-testing.md` | 31 | [guides/testing/live.md](../../../docs/guides/testing/live.md), [reference/testing/live.md](../../../docs/reference/testing/live.md) |
| `docs/guides/logging.md` | 9 | [reference/results/observability.md](../../../docs/reference/results/observability.md) |
| `docs/guides/megaclient.md` | 18 | [guides/integration/multi-service.md](../../../docs/guides/integration/multi-service.md), [reference/client/construction.md](../../../docs/reference/client/construction.md), [reference/client/response-dto-catalog.md](../../../docs/reference/client/response-dto-catalog.md) |
| `docs/guides/migration.md` | 5 | [migration/unreleased.md](../../../docs/migration/unreleased.md), [migration/v0.2.0-alpha.1.md](../../../docs/migration/v0.2.0-alpha.1.md) |
| `docs/guides/naming-strategy.md` | 6 | [reference/serialization/request-parts.md](../../../docs/reference/serialization/request-parts.md) |
| `docs/guides/operation-inventory.md` | 22 | [reference/client/catalogs.md](../../../docs/reference/client/catalogs.md), [reference/client/operation-inventory.md](../../../docs/reference/client/operation-inventory.md), [reference/client/response-dto-catalog.md](../../../docs/reference/client/response-dto-catalog.md) |
| `docs/guides/pagination.md` | 15 | [reference/execution/pagination.md](../../../docs/reference/execution/pagination.md) |
| `docs/guides/provider-analysis.md` | 12 | [guides/sdk/analysis.md](../../../docs/guides/sdk/analysis.md) |
| `docs/guides/provider-async-await.md` | 17 | [guides/recipes/continuation.md](../../../docs/guides/recipes/continuation.md), [reference/execution/continuation-await.md](../../../docs/reference/execution/continuation-await.md), [reference/execution/continuation-state.md](../../../docs/reference/execution/continuation-state.md) |
| `docs/guides/provider-catalogs.md` | 12 | [reference/client/catalogs.md](../../../docs/reference/client/catalogs.md) |
| `docs/guides/provider-checklist.md` | 8 | [guides/sdk/coverage.md](../../../docs/guides/sdk/coverage.md) |
| `docs/guides/provider-methodology.md` | 32 | [guides/integration/multi-service.md](../../../docs/guides/integration/multi-service.md), [guides/sdk/analysis.md](../../../docs/guides/sdk/analysis.md), [guides/sdk/coverage.md](../../../docs/guides/sdk/coverage.md), [guides/sdk/design.md](../../../docs/guides/sdk/design.md), [guides/testing/live.md](../../../docs/guides/testing/live.md), [guides/testing/unit.md](../../../docs/guides/testing/unit.md), [reference/attributes/request.md](../../../docs/reference/attributes/request.md), [reference/auth/credentials.md](../../../docs/reference/auth/credentials.md), [reference/client/catalogs.md](../../../docs/reference/client/catalogs.md), [reference/client/operation-inventory.md](../../../docs/reference/client/operation-inventory.md), [reference/client/versioning.md](../../../docs/reference/client/versioning.md), [reference/execution/continuation-state.md](../../../docs/reference/execution/continuation-state.md), [reference/execution/pagination.md](../../../docs/reference/execution/pagination.md), [reference/results/errors.md](../../../docs/reference/results/errors.md), [reference/results/handles.md](../../../docs/reference/results/handles.md) |
| `docs/guides/quickstart.md` | 8 | [guides/quickstart.md](../../../docs/guides/quickstart.md), [reference/attributes/http.md](../../../docs/reference/attributes/http.md) |
| `docs/guides/redis-rate-limit.md` | 6 | [reference/integrations/redis.md](../../../docs/reference/integrations/redis.md) |
| `docs/guides/request-pipeline.md` | 9 | [reference/request/composition.md](../../../docs/reference/request/composition.md) |
| `docs/guides/requests.md` | 18 | [glossary/requests.md](../../../docs/glossary/requests.md), [reference/request/declaration.md](../../../docs/reference/request/declaration.md) |
| `docs/guides/resources.md` | 7 | [reference/client/resources.md](../../../docs/reference/client/resources.md) |
| `docs/guides/retries-rate-limit.md` | 15 | [reference/execution/deadlines.md](../../../docs/reference/execution/deadlines.md), [reference/execution/rate-limit.md](../../../docs/reference/execution/rate-limit.md), [reference/execution/retry.md](../../../docs/reference/execution/retry.md) |
| `docs/guides/serialization.md` | 36 | [reference/dto/scalars.md](../../../docs/reference/dto/scalars.md), [reference/serialization/body.md](../../../docs/reference/serialization/body.md), [reference/serialization/dto-output.md](../../../docs/reference/serialization/dto-output.md), [reference/serialization/receiver-output.md](../../../docs/reference/serialization/receiver-output.md), [reference/serialization/request-parts.md](../../../docs/reference/serialization/request-parts.md), [reference/serialization/uri-query.md](../../../docs/reference/serialization/uri-query.md) |
| `docs/guides/testing.md` | 18 | [development/testing.md](../../../development/testing.md), [glossary/testing.md](../../../docs/glossary/testing.md), [guides/testing/unit.md](../../../docs/guides/testing/unit.md), [reference/testing/fixtures.md](../../../docs/reference/testing/fixtures.md), [reference/testing/live.md](../../../docs/reference/testing/live.md), [reference/testing/mocking.md](../../../docs/reference/testing/mocking.md) |
| `docs/guides/transport.md` | 11 | [reference/execution/transport.md](../../../docs/reference/execution/transport.md) |
| `docs/guides/troubleshooting.md` | 12 | [guides/troubleshooting.md](../../../docs/guides/troubleshooting.md) |
| `docs/guides/use-cases.md` | 7 | [guides/integration/multi-service.md](../../../docs/guides/integration/multi-service.md), [guides/recipes/continuation.md](../../../docs/guides/recipes/continuation.md), [guides/recipes/files.md](../../../docs/guides/recipes/files.md), [guides/recipes/pagination.md](../../../docs/guides/recipes/pagination.md), [reference/execution/batch-pool.md](../../../docs/reference/execution/batch-pool.md), [start/README.md](../../../docs/start/README.md) |
| `docs/guides/validation.md` | 12 | [reference/client/validation.md](../../../docs/reference/client/validation.md) |
| `docs/guides/versioning.md` | 13 | [reference/client/versioning.md](../../../docs/reference/client/versioning.md) |
| `docs/technical/README.md` | 3 | [development/README.md](../../../development/README.md) |
| `docs/technical/architecture.md` | 11 | [development/architecture.md](../../../development/architecture.md) |
| `docs/technical/attributes.md` | 11 | [development/attributes.md](../../../development/attributes.md) |
| `docs/technical/caching-retry.md` | 6 | [development/caching-retry.md](../../../development/caching-retry.md) |
| `docs/technical/error-handling.md` | 5 | [development/error-handling.md](../../../development/error-handling.md) |
| `docs/technical/execution.md` | 7 | [development/execution.md](../../../development/execution.md) |
| `docs/technical/pipeline.md` | 6 | [development/pipeline.md](../../../development/pipeline.md) |
