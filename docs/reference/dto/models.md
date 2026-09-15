# Модели DTO и наследование

Краткий гайд по DTO, маппингу и валидации.

Для моделей без атрибутов используйте [внешний набор правил](../../guides/dto/plain-models.md):
он задаёт mapping, дочерние DTO, strict, extras и проверку присутствия.
`DTO::from()` не наследует набор клиента; standalone-вход с набором — `Hydrator::forRules()`.

## Базовый DTO
```php
use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;

final readonly class UserDto extends AbstractResponseDto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
```

## Inheritance и hydration
Hydrator в `apisutra` использует модель `constructor-first`, но теперь поддерживает и inherited public properties вне constructor chain.

Это означает:
- всё, что покрывается effective constructor chain, инициализируется через конструктор
- оставшиеся публичные гидрируемые свойства могут быть доинициализированы hydrator-ом напрямую
- это особенно полезно для DTO-иерархий, где базовый класс держит общие поля, а конечный DTO добавляет свои блоки данных

Пример:
```php
abstract readonly class BaseBlockDto extends AbstractResponseDto
{
    public function __construct(
        public ReportFoundState $found,
        public ReportBlockStatus $status,
    ) {}
}

final readonly class OtherLastNamesBlockDto extends BaseBlockDto
{
    #[From('result')]
    public ?OtherLastNamesResultDto $result;
}
```

Практические правила:
- constructor-first остаётся основным и рекомендуемым контрактом
- fallback assignment работает только для оставшихся public data properties
- для nullable non-constructor property при `Missing` hydrator инициализирует `null`
- для non-nullable missing non-constructor property hydrator бросает явную ошибку
