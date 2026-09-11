# Extensions (жизненный цикл и конфликты)

Extensions — это единый механизм расширения SDK: касты, хуки, обработчики ответов
и кастомные атрибуты.

## Жизненный цикл
1) `checkDependencies()`  
2) `register()` — регистрация компонентов  
3) `boot()` — лениво, когда впервые требуется response‑handler  
4) `isEnabled()` — проверяется при использовании

## Что можно регистрировать
- касты (`registerCast`)
- хуки (`registerHook`)
- обработчики ответов (`registerResponseHandler`)
- обработчики атрибутов (`registerAttributeHandler`)

## Response handlers и приоритет
Поиск обработчика происходит по `Content-Type` с приоритетом:
1) точное совпадение (`application/json`)
2) тип‑маска (`application/*`)
3) `*`

Если MIME не совпал, используется `supports()` обработчика.

## Конфликты и override
Если для MIME уже есть обработчик:
- без `override` → `ExtensionConflictException`
- с `override = true` — обработчик будет заменён

По умолчанию обработчики появляются только через подключённые extensions.
`override = true` используйте, когда хотите заменить поведение
другого extension для того же `Content-Type`.

## Пример расширения
```php
use Brahmic\ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Hooks\Hook;
use Brahmic\ApiSutra\Enums\Hooks\HookPriority;
use Brahmic\ApiSutra\Extensions\ExtensionContext;

final class MetricsExtension implements ExtensionInterface
{
    public function getName(): string
    {
        return 'metrics';
    }

    public function register(ExtensionContext $context): void
    {
        $context->registerHook(Hook::AfterResponse, new MetricsHook(), HookPriority::Last);
        $context->registerResponseHandler('application/vnd.metrics+json', new MetricsResponseHandler());
    }

    public function boot(ClientConfig $config): void
    {
        // Инициализация после регистрации
    }

    public function checkDependencies(): void
    {
        // Проверка зависимостей
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
```

## Где детали
- Настройка extensions: `docs/guides/client-config/extensions.md`
- Кастомные атрибуты: `docs/technical/attributes.md`
