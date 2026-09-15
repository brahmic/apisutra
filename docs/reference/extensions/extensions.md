# Публичные расширения

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

Extensions — это единый механизм расширения SDK: касты, хуки, обработчики ответов
и кастомные атрибуты.

## Жизненный цикл
1) `checkDependencies()`
2) `register()` — регистрация компонентов
3) `boot()` — лениво, когда впервые требуется response‑handler
4) `isEnabled()` — проверяется при использовании

## Что можно регистрировать
- касты сериализации запросов (`registerCast`); участие источников в гидратации
  описано в [справке casts](../serialization/casts.md#регистрация-кастов)
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
