# Публичные расширения

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
