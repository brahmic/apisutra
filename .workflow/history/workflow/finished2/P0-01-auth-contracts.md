# P0-01: Authenticator/CacheAware — единые контракты

## Проблема
- В `../docs/architecture/01-contracts.md` и `../docs/features/authentication.md`
  ранее были разные сигнатуры AuthenticatorInterface и CacheAwareInterface.

## Канонический источник
- `../docs/architecture/01-contracts.md`

## Рекомендуемое решение (с аргументацией)
- Зафиксировать `shouldRefresh(): bool`, `getRefreshRequest(): ?RequestInterface`,
  `processTokenResponse(ResponseDtoInterface $response): void`.
  Аргументы: preemptive refresh не зависит от ответа; контракт не должен
  зависеть от `AbstractRequest`; токен‑ответ типизируется DTO.
- Зафиксировать CacheAwareInterface как `setCache()` + `getCacheKey()`.
  Аргументы: явный ключ устраняет коллизии, инъекция кеша остаётся прозрачной.
- Привести `../docs/features/authentication.md` и `../docs/glossary.md` к канону.
