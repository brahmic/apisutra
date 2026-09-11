# Extensions

Расширения позволяют подключать обработчики и дополнительное поведение без
изменения ядра клиента.

## Для каких задач
- регистрация кастов, хуков и response-handlers
- добавление своих обработчиков атрибутов
- централизованное подключение кросс-срезов (логирование, метрики, трассировка)

Если кастомизаций не нужно — `extensions` можно не задавать, поведение по умолчанию
не изменится.

Подробный гайд: `docs/guides/extensions.md`.

## Рекомендуемый вариант: отдельный класс
Импорты опущены — ниже показан каркас и назначение методов.
```php
final class ExampleExtension implements ExtensionInterface
{
    public function getName(): string
    {
        // Уникальный ключ расширения: регистрация, конфликты, логирование.
        return 'example';
    }

    public function register(ExtensionContext $context): void
    {
        // Регистрация кастов, хуков, обработчиков ответов и атрибутов.
        // Пример: $context->registerHook(...); $context->registerCast(...);
    }

    public function boot(ClientConfig $config): void
    {
        // Инициализация после регистрации (кеши, клиенты, подготовка ресурсов).
    }

    public function checkDependencies(): void
    {
        // Проверка зависимостей/конфигурации, при проблеме — исключение.
    }

    public function isEnabled(): bool
    {
        // Возвращает, активно ли расширение в текущих условиях.
        return true;
    }
}
```

## Базовая настройка
```php
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    extensions: [
        new ExampleExtension(),
    ],
);
```

