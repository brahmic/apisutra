# Касты (Type Casting)

## Обзор

Касты преобразуют значения между форматом API и PHP-типами:
- **Гидрация (hydrate):** JSON → PHP объект
- **Сериализация (serialize):** PHP объект → JSON

---

## CastInterface

```php
interface CastInterface
{
    /**
     * JSON → PHP (при гидрации DTO)
     */
    public function hydrate(mixed $value, ?PipelineContext $ctx = null): mixed;
    
    /**
     * PHP → JSON (при сериализации запроса)
     */
    public function serialize(mixed $value, ?PipelineContext $ctx = null): mixed;
}
```

---

## Автоматические касты

SDK автоматически применяет касты по типу свойства:

| Тип свойства | Каст | Поведение |
|--------------|------|-----------|
| `Carbon`, `DateTime` | `DateTimeCast` | ISO 8601 ↔ объект |
| `BackedEnum` | `EnumCast` | string/int ↔ enum |
| `int` | `IntegerCast` | string → int |
| `float` | `FloatCast` | string → float |
| `bool` | `BooleanCast` | 'true'/1 → bool |
| `DTO класс` | Рекурсивная гидрация | |

**Атрибут `#[Cast]` нужен только для кастомизации.**

---

## Атрибут #[Cast]

### Простое использование

```php
readonly class DataDto extends AbstractDto
{
    #[Cast(DateTimeCast::class)]
    public ?Carbon $caseDate;
    
    #[Cast(CourtParticipantRoleCast::class)]
    public ?CourtParticipantRole $role;
}
```

### С параметрами

Параметры передаются в конструктор каста:

```php
readonly class DataDto extends AbstractDto
{
    // Нестандартный формат даты
    #[Cast(DateTimeCast::class, format: 'd.m.Y')]
    public ?Carbon $birthDate;
    
    #[Cast(DateTimeCast::class, format: 'd.m.Y H:i:s', timezone: 'Europe/Moscow')]
    public ?Carbon $createdAt;
}
```

---

## Создание кастомного каста

```php
class DateTimeCast implements CastInterface
{
    public function __construct(
        private string $format = DATE_ATOM,
        private ?string $timezone = null,
    ) {}
    
    public function hydrate(mixed $value, PipelineContext $ctx): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        
        $date = Carbon::createFromFormat($this->format, $value);
        
        if ($this->timezone) {
            $date->setTimezone($this->timezone);
        }
        
        return $date;
    }
    
    public function serialize(mixed $value, PipelineContext $ctx): ?string
    {
        return $value?->format($this->format);
    }
}
```

### Enum каст

```php
class CourtParticipantRoleCast implements CastInterface
{
    public function hydrate(mixed $value, PipelineContext $ctx): ?CourtParticipantRole
    {
        return $value !== null 
            ? CourtParticipantRole::tryFrom($value) 
            : null;
    }
    
    public function serialize(mixed $value, PipelineContext $ctx): ?string
    {
        return $value?->value;
    }
}
```

### Money каст

```php
class MoneyCast implements CastInterface
{
    public function __construct(
        private string $currency = 'RUB',
    ) {}
    
    public function hydrate(mixed $value, PipelineContext $ctx): ?Money
    {
        if ($value === null) {
            return null;
        }
        
        // API возвращает копейки
        return new Money(
            amount: (int) $value,
            currency: $this->currency,
        );
    }
    
    public function serialize(mixed $value, PipelineContext $ctx): ?int
    {
        return $value?->getAmount();
    }
}
```

---

## Глобальная регистрация

Регистрация кастов по типу в `ClientConfig`:

```php
$config = new ClientConfig(
    baseUrl: '...',
    casts: [
        Money::class => MoneyCast::class,
        DateTimeInterface::class => DateTimeCast::class,
        CustomEnum::class => EnumCast::class,
    ],
);
```

**Применение:**
- Любое свойство с типом `Money` автоматически использует `MoneyCast`
- Не нужно указывать `#[Cast]` на каждом свойстве

---

## Приоритет разрешения

```
1. #[Cast] на свойстве — высший приоритет
2. Глобальный каст из ClientConfig::casts
3. Встроенные касты SDK (Carbon, BackedEnum, etc.)
4. Без каста (as is)
```

**Пример:**

```php
$config = new ClientConfig(
    casts: [
        Carbon::class => DateTimeCast::class, // ISO 8601 по умолчанию
    ],
);

readonly class DataDto extends AbstractDto
{
    // Использует глобальный DateTimeCast (ISO 8601)
    public ?Carbon $createdAt;
    
    // Переопределяет — свой формат
    #[Cast(DateTimeCast::class, format: 'd.m.Y')]
    public ?Carbon $birthDate;
}
```

---

## Встроенные касты SDK

```php
// Brahmic\ApiSutra\Casts\

DateTimeCast      // DateTime/Carbon, настраиваемый формат
EnumCast          // BackedEnum
BooleanCast       // 'true'/'1'/1 → bool
IntegerCast       // string → int
FloatCast         // string → float
JsonCast          // JSON string → array
```

---

## Когда нужен #[Cast]

| Ситуация | Атрибут нужен? |
|----------|---------------|
| `Carbon` свойство, ISO 8601 | Нет — автокаст |
| `Carbon` свойство, формат `d.m.Y` | Да — кастомный формат |
| `BackedEnum` свойство | Нет — автокаст |
| `Money` с глобальной регистрацией | Нет — из config |
| Кастомный тип без регистрации | Да |

---

## Пример в контексте DTO

```php
readonly class PersonDataDto extends AbstractDto
{
    public ?string $caseId;
    
    // Автокаст — Carbon + ISO 8601
    public ?Carbon $createdAt;
    
    // Явный каст — нестандартный формат
    #[Cast(DateTimeCast::class, format: 'd.m.Y')]
    public ?Carbon $birthDate;
    
    // Автокаст enum
    public ?PersonStatus $status;
    
    // Глобально зарегистрированный Money
    public ?Money $balance;
    
    // Переименование + каст
    #[From('court_role')]
    #[Cast(CourtParticipantRoleCast::class)]
    public ?CourtParticipantRole $role;
}
```

---

## Резюме

| Элемент | Назначение |
|---------|------------|
| `CastInterface` | Интерфейс для кастомных преобразований |
| `#[Cast(Class::class)]` | Явное указание каста на свойстве |
| `ClientConfig::casts` | Глобальная регистрация по типу |
| `hydrate()` | JSON → PHP при гидрации |
| `serialize()` | PHP → JSON при сериализации |
