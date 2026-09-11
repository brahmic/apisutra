# Workflow: согласование документации

Цель: устранить критичные несогласованности и зафиксировать единый канон
перед реализацией.

Канон (если в задаче не указано иначе):
- интерфейсы и сигнатуры: `../docs/architecture/01-contracts.md`
- pipeline: `../docs/architecture/03-pipeline.md`
- конфигурация: `../docs/architecture/06-config.md`

## P0 — блокирует реализацию

- `P0-01-auth-contracts.md` — Authenticator/CacheAware.
- `P0-02-pagination-interface.md` — PaginableInterface.
- `P0-03-castinterface-names.md` — CastInterface имена методов.
- `P0-04-computed-context-nullability.md` — nullable context в computed().
- `P0-05-attribute-from-naming.md` — `#[From]` vs `#[NameFrom]`.
- `P0-06-shouldretry-signature.md` — сигнатура shouldRetry().
- `P0-07-pipelinecontext-structure.md` — структура и мутабельность PipelineContext.
- `P0-08-composite-aggregate-signature.md` — сигнатура aggregate().
- `P0-09-dependson-processdependencies-signature.md` — сигнатура processDependencies().

## P1 — важно до старта реализации

- `P1-01-resultstatus-enum.md` — ResultStatus enum.
- `P1-02-ratelimitbehavior-enum.md` — RateLimitBehavior enum.
- `P1-03-queryarrayformat-glossary.md` — QueryArrayFormat в глоссарии.
- `P1-04-auth-retry-config.md` — authRetryOn401/authRetryAttempts в ClientConfig.
- `P1-05-refresh-failure.md` — поведение при неуспешном refresh.
- `P1-06-hydrator-castregistry-scope.md` — scope Hydrator/CastRegistry.
- `P1-07-attribute-metadata-cache.md` — интеграция AttributeMetadataCache.
- `P1-08-extension-lifecycle.md` — lifecycle расширений.
- `P1-09-retry-ownership.md` — где реализован retry.
- `P1-10-validation-strategy.md` — стратегия валидации (Result vs Exception).

## P2 — полнота и качество документации

- `P2-01-glossary-api-alignment.md` — AbstractClient/AbstractRequest в глоссарии.
- `P2-02-glossary-missing-concepts.md` — недостающие сущности в глоссарии.
