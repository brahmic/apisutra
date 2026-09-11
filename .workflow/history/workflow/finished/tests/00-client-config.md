# Client & Config — план тестирования

## Scope
- `AbstractClient`
- `ClientConfig` и config‑VO (`CacheConfig`, `RetryConfig`, `RateLimitConfig`, `BatchConfig`, `PoolConfig`, `ArchiveConfig`)
- `CastRegistry` и регистрация кастов
- `AttributeMetadataCache` binding

## Invariants
- Environment влияет на кеш метаданных (Local/Testing — off, Production — on).
- `ClientConfig::fromLaravel()` корректно подставляет defaults.
- `AbstractClient` перестраивает pipeline при `fake/record/playback`.

## Unit tests
- `ClientConfig::with()` переопределяет значения без побочных эффектов.
- `ClientConfig::fromLaravel()` использует `resolveLaravelDebug/Environment`.
- `AbstractClient::getAttributeMetadataCache()` возвращает единый кеш.

## Integration tests
- `fake()` → `MockTransport`, запросы идут через mock.
- `record()`/`playback()` переключают транспорт и конфиг фикстур.

## Edge cases
- `cache` передан как `CacheConfig` vs `CacheInterface`.
- `auth` implements `CacheAwareInterface` → cache injected.

## Fixtures/Mocks
- In‑memory PSR‑16 cache.
- `MockTransport` responses.

## Priority
- P0: environment/cache binding.
- P1: fake/record/playback.
