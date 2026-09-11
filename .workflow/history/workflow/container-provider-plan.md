## План работ: ContainerProvider для изоляции от Laravel

### Цель
Убрать прямые зависимости от `app()`/`Illuminate\Container\Container` из core, сохранив поведение и DX «из коробки» для Laravel.

### Последовательность
1) **Контракты и инфраструктура**
   - Добавить `ContainerProviderInterface` (минимальный контракт).
   - Реализовать:
     - `NullContainerProvider` (без зависимостей).
     - `LaravelContainerProvider` (адаптер для контейнера).
     - `ContainerProviderRegistry` с авто‑детектом и override.
2) **Интеграция в core**
   - `AbstractRequest::resolveClientResolver()` → через registry/provider.
   - `ClientDiscoveryService::resolveBasePath()` → через provider.
   - `Validator::resolveFactory()` → через provider.
   - `ClientConfig::resolveLaravelDebug/Environment()` → через provider (fallback оставить).
3) **Сброс глобального состояния в тестах**
   - В `TestTrait` добавить `ContainerProviderRegistry::reset()`.
4) **Тесты**
   - Registry: авто‑детект, override, reset.
   - AbstractRequest: резолв клиента через provider без `setClient()`.

### Риски и нюансы
- **Глобальное состояние** registry: обязательно сбрасывать в тестах.
- **Совместимость**: все пути должны иметь fallback на текущую логику.
- **Auto‑detect**: работать только при наличии Laravel контейнера; иначе безопасный `NullContainerProvider`.
- **Валидация**: не менять порядок и правила (поведение должно остаться прежним).

### Критерии готовности
- `app()` не используется в core‑классах напрямую.
- Все тесты зелёные.
- В Laravel — нулевой бойлерплейт.
