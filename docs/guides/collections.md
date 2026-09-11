# Коллекции

Этот гайд описывает typed‑коллекции в ApiSutra и их интеграцию с DTO.

## Зачем
Typed‑коллекции дают предсказуемый контракт:
- IDE и статанализ знают точный тип элементов
- меньше ошибок при работе с ответами
- удобные методы `first()/count()/isEmpty()`

## AbstractTypedCollection
Базовый typed‑контейнер. Требует указать класс элемента и делает runtime‑guard.

```php
use Brahmic\ApiSutra\Collections\AbstractTypedCollection;

final readonly class OrderItemCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return OrderItemDto::class;
    }
}
```

### Доступные методы
- `all(): array<int|string, T>`
- `first(callback|null, default|null): ?T|mixed`
- `get(key, default?): T|mixed`
- `has(keys): bool`, `hasAny(keys): bool`
- `only(keys): static`, `except(keys): static`
- `count(): int`, `isEmpty(): bool`
- `map(callable(T): T): static`
- `filter(callback|null): static` (без callback удаляет falsy)
- `contains(value|callback|key, operator, value): bool`
- `firstWhere(key, operator?, value?): ?T`
- `pluck(value, key?): RawCollection`
- `keyBy(key|callable): static`
- `sortBy(key|callable, options?, desc=false): static`
- `sortByDesc(key|callable, options?): static`
- `unique(key|callable|null, strict=false): static`
- `values(): static`
- `mapToArray(callable(T): mixed): array`
- `fromArray(array $items): static`
- `toArray(): array`

### Guard
В конструкторе проверяется, что все элементы — `instanceof itemClass()`.
При нарушении guard выбрасывается `ConfigurationException`.

### Общая коллекция для похожих DTO
Если есть несколько очень похожих DTO (например, записи прав и обременений),
и хочется общий класс коллекции — используйте **общую абстракцию**:
интерфейс или базовый DTO.

```php
interface RightRecordInterface {}

final readonly class RegistrationRecordDto implements RightRecordInterface {}
final readonly class EncumbranceRecordDto implements RightRecordInterface {}

final readonly class RightRecordCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return RightRecordInterface::class;
    }
}
```

Когда применять:
- есть общий контракт и поведение для элементов;
- нужен один тип коллекции в разных местах;
- важна типобезопасность и guard.

Когда **не** применять:
- элементы не имеют общего контракта;
- нужны “контекстные” типы (тогда лучше `RawCollection`).

## Интеграция с DTO
Чтобы DTO получал коллекцию вместо массива, используйте `#[Nested]`:

```php
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;

final readonly class OrderDto extends AbstractResponseDto
{
    public function __construct(
        #[Nested(type: OrderItemDto::class)]
        public OrderItemCollection $items,
    ) {}
}
```

## RawCollection (escape‑hatch)
Если нужен контейнер без типовой проверки — используйте `RawCollection`.
Он поддерживает те же базовые методы, но не выполняет guard.

Для полиморфных массивов (`#[Nested(discriminator: ..., map: ...)]`) правила такие же,
как для обычного `array`: работают `discriminatorMode` (`Value`/`Key`) и
`unknownVariant` (`KeepRaw`/`Skip`/`Error`).

## Сериализация
`toArray()` делает глубокую сериализацию:
- DTO → `toArray()`
- `JsonSerializable` → `jsonSerialize()`
- остальное → как есть

## Приоритеты
Typed‑коллекции не влияют на `#[Cast]` и registry‑касты.
Они лишь задают тип контейнера для списков.

## Ключи и индексация
`map()` и `filter()` **сохраняют ключи**, как в Laravel.  
Если нужна переиндексация — используйте `values()`.
