# NamingStrategy

NamingStrategy определяет, как SDK преобразует имена свойств
в ключи запроса и обратно.

## Доступные режимы
- `None` — без преобразований
- `SnakeCase` — `camelCase` → `snake_case`

По умолчанию используется `None`.

## Где применяется
- **Запросы**: при сборке query/body, если не задан `name`
- **DTO**: при гидрации, если нет явного пути в `From`/`Map`/`Nested` или `FieldRule::from()`
- **DTO‑сериализация**: если нет `#[To]`; для DX DTO рекомендуемая naming policy задаётся через `DtoSerializationProfile`

## Как переопределять
- `#[Query(name: ...)]` / `#[Body(nested: ...)]` — для запросов
- `#[From('data.id')]` — для входящих данных (DTO)
- `FieldRule::from('data.id')` — для входящих данных во внешнем наборе
- `#[To('user_id')]` — для сериализации DTO

## Пример
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\NamingStrategy;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    namingStrategy: NamingStrategy::SnakeCase,
);
```

## Рекомендации
- Пример `ClientConfig::namingStrategy` выше настраивает запросы. Для входящих DTO
  задайте naming в `DtoHydrationProfile` или `RulePolicy` внешнего набора;
  [приоритеты правил](hydration-rules.md#policy-и-строгие-типы).
- Если API уже использует `snake_case`, включайте `SnakeCase`.
- Для mixed‑API используйте `None` и задавайте `#[From]/#[To]/#[Query]` точечно.
- Для provider SDK DX DTO naming policy рекомендуется централизовать через `DtoSerializationProfile`,
  а request-level fallback — через `ClientConfig`.
