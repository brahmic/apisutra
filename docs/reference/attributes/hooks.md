# Хуки и точки вызова

## Сигнатуры и targets

Имена классов относятся к `Brahmic\ApiSutra\Attributes\Hooks`.
Сигнатуры показывают параметры и defaults конструктора; target указывает допустимое
место атрибута. Поведение и приоритеты описаны в тематических ссылках ниже.

| Атрибут | Target | Конструктор |
| --- | --- | --- |
| AfterHydrate | CLASS  /  REPEATABLE | `AfterHydrate(string $handler, HookPriority $priority = HookPriority::Normal, ?string $name = null)` |
| AfterResponse | CLASS  /  REPEATABLE | `AfterResponse(string $handler, HookPriority $priority = HookPriority::Normal, ?string $name = null)` |
| BeforeHydrate | CLASS  /  REPEATABLE | `BeforeHydrate(string $handler, HookPriority $priority = HookPriority::Normal, ?string $name = null)` |
| BeforeSend | CLASS  /  REPEATABLE | `BeforeSend(string $handler, HookPriority $priority = HookPriority::Normal, ?string $name = null)` |

Атрибуты для подключения hook‑обработчиков к жизненному циклу запроса.
Все атрибуты повторяемые (`IS_REPEATABLE`).

## Когда использовать
- **BeforeSend** — добавить/изменить заголовки, trace‑id, подписи.
- **AfterResponse** — логирование, метрики, аудит.
- **BeforeHydrate** — нормализация данных до DTO.
- **AfterHydrate** — пост‑обработка DTO.

Подробный гайд по централизованным хукам: `docs/guides/hooks.md`.

## BeforeSend
**Параметры:**
- `handler: string` — класс обработчика
- `priority: HookPriority = Normal`
- `name?: string` — имя для защиты от дубликатов

## AfterResponse
**Параметры:** как у `BeforeSend`

## BeforeHydrate
**Параметры:** как у `BeforeSend`

## AfterHydrate
**Параметры:** как у `BeforeSend`

Пример:
```php
use Brahmic\ApiSutra\Attributes\Hooks\AfterResponse;
use Brahmic\ApiSutra\Attributes\Hooks\BeforeSend;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Hooks\HookPriority;

#[BeforeSend(AddTraceHook::class, priority: HookPriority::First, name: 'trace')]
#[AfterResponse(LogResponseHook::class)]
final class SomeRequest extends AbstractRequest {}
```
