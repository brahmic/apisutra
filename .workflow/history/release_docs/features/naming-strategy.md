# NamingStrategy

Автоматическая конвертация имён между PHP и JSON.

## Проблема

PHP convention — camelCase. Многие API используют snake_case. Без автоконвертации разработчик вручную маппит каждое поле.

## Решение

NamingStrategy — глобальное правило конвертации имён в ClientConfig.

## Стратегии

```php
enum NamingStrategy: string
{
    case None = 'none';           // как есть, 1:1
    case SnakeCase = 'snake_case'; // camelCase ↔ snake_case
}
```

| Стратегия | PHP → JSON | JSON → PHP |
|-----------|-----------|-----------|
| `None` | как есть | как есть |
| `SnakeCase` | `orderItems` → `order_items` | `order_items` → `orderItems` |

## Конфигурация

```php
$client = new Client(config: new ClientConfig(
    baseUrl: 'https://api.example.com',
    namingStrategy: NamingStrategy::SnakeCase,
));
```

**Default:** `None` — явное лучше неявного.

## Где применяется

- **Request → API:** Сериализация свойств запроса в query/body
- **API → DTO:** Гидрация полей ответа в свойства DTO

## Переопределение на поле

Глобальная стратегия переопределяется явным указанием имени:
`#[From]` и `#[Query]` имеют приоритет над NamingStrategy.

**В Request:**
```php
#[Query('custom_field_name')]
public string $customField;  // → custom_field_name, игнорирует strategy
```

**В DTO:**
```php
#[From('custom_field_name')]
public string $customField;  // ← custom_field_name, игнорирует strategy
```

## Когда что использовать

| API провайдера | Стратегия |
|----------------|-----------|
| Консистентный snake_case | `SnakeCase` |
| Консистентный camelCase | `None` |
| Винегрет (смешанный) | `None` + явный маппинг |

## Рекомендация

1. Начните с `None` — предсказуемо
2. Если API консистентный — переключите на `SnakeCase`
3. Для отдельных полей — используйте явный маппинг через атрибуты
