# Хуки и точки вызова

Hooks — это централизованные обработчики этапов пайплайна. Они позволяют
добавлять поведение без изменения кода запросов или DTO.

## Типы хуков
- `BeforeSend` — перед отправкой HTTP‑запроса
- `AfterResponse` — после получения ответа
- `BeforeHydrate` — перед гидрацией DTO (можно вернуть изменённые данные)
- `AfterHydrate` — после гидрации DTO

## HookRegistry: регистрация
```php
use Brahmic\ApiSutra\Enums\Hooks\Hook;
use Brahmic\ApiSutra\Enums\Hooks\HookPriority;
use Brahmic\ApiSutra\Hooks\HookRegistry;

$hooks = new HookRegistry(resolver: fn (string $class) => new $class());

// Глобальный хук
$hooks->on(Hook::BeforeSend, AddTraceHeader::class, priority: HookPriority::First, name: 'trace');

// Хук только для конкретного запроса
$hooks->on(Hook::AfterResponse, LogResponse::class, for: [GetUser::class]);

// Хук только для конкретного DTO
$hooks->on(Hook::BeforeHydrate, UnwrapData::class, forDto: UserDto::class);
```

### Имя и удаление
`name` защищает от дубликатов и помогает удалить хук:
```php
$hooks->remove(Hook::BeforeSend, 'trace');
```

## Порядок исполнения
Порядок для каждого этапа:
1) **Глобальные** хуки
2) **По запросу** (`for`)
3) **По DTO** (`forDto`)
4) **Атрибуты hooks** на запросе
5) **Методы запроса** (`beforeSend/afterResponse/beforeHydrate/afterHydrate`)

Приоритеты внутри группы: `First → Normal → Last`.

## BeforeHydrate: изменение данных
`BeforeHydrate` может вернуть изменённые данные:
```php
use Brahmic\ApiSutra\Contracts\Interfaces\Hooks\BeforeHydrateHookInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class UnwrapData implements BeforeHydrateHookInterface
{
    public function handle(PipelineContext $context): array
    {
        return $context->response?->json('data') ?? [];
    }
}
```

Если `BeforeHydrate` не задан — данные идут в гидрацию как есть.
В методе запроса `beforeHydrate()` по умолчанию просто возвращает входной массив.

Для успешного ответа без DTO hook вызывается только при данных-массиве. `null`,
скаляры JSON и `text/plain` проходят без `BeforeHydrate`; `AfterResponse` и
`AfterHydrate` сохраняются. Подробности и правила пустого тела — в
[контракте успешного ответа](../results/handles.md#успешный-ответ-без-dto).

## Где атрибуты
Атрибуты‑хуки описаны отдельно: [атрибуты хуков](../attributes/hooks.md).
