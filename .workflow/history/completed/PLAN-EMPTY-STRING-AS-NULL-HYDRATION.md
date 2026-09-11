# План: empty string as null в DTO hydration

## Проблема и ожидания
В ряде provider payload пустая строка `''` по смыслу означает не "пустое текстовое значение",
а "значения нет".

Сейчас `apisutra` различает только три состояния:

- `Missing` — ключ отсутствует
- `Null` — ключ есть, значение `null`
- `Present` — значение найдено

Пустая строка `''` сейчас попадает в `Present` и не нормализуется автоматически.

Ожидание:
- хотим иметь возможность трактовать `''` как `null`
- хотим делать это либо точечно на свойстве, либо централизованно на уровне hydration policy
- не хотим ломать backward-compatible default поведение

---

## Цель
Добавить в `apisutra` управляемую string-normalization модель для hydration:

1. default поведение не меняется
2. property-level атрибут позволяет явно включить `'' -> null`
3. profile-level hydration policy позволяет централизованно включить такое поведение
4. правило применяется до cast/nested/date hydration
5. поведение для non-nullable полей остаётся предсказуемым

---

## Предлагаемая модель

## 1. Property-level атрибут
Добавить атрибут:

- `#[EmptyStringAsNull]`

Минимальная форма:

```php
#[EmptyStringAsNull]
public ?string $name;
```

Опционально можно сразу предусмотреть:

```php
#[EmptyStringAsNull(blank: true)]
```

где:
- `blank: false` -> только `''`
- `blank: true` -> `''` и строки из пробелов

## 2. Profile-level hydration policy
Добавить в hydration policy глобальное правило.

Рабочий вариант:

```php
enum EmptyStringBehavior: string
{
    case Keep = 'keep';
    case NullIfEmpty = 'null_if_empty';
    case NullIfBlank = 'null_if_blank';
}
```

И поле в `DtoHydrationPolicy`:

```php
public EmptyStringBehavior $emptyStringBehavior = EmptyStringBehavior::Keep
```

## 3. Приоритеты
Рекомендуемый порядок:

1. `#[Cast(...)]`
2. `#[EmptyStringAsNull(...)]`
3. `DtoHydrationPolicy::emptyStringBehavior`
4. дефолт `Keep`

Почему так:
- `Cast` остаётся самым сильным explicit transform
- field-level rule сильнее глобальной policy
- default в ядре остаётся консервативным

---

## Где применять в пайплайне

Применять нужно на этапе raw-input normalization,
после того как значение найдено в payload, но до:

- `applyCasts(...)`
- `hydrateNested(...)`
- enum/date transforms

То есть примерно в `Hydrator::hydrate()` между:

- `resolveValueWithFallbacks(...)`
- и последующей hydration/cast веткой

Это важно, чтобы:
- normalization работала одинаково для constructor-backed и remaining properties
- она не была размазана по кастам
- логика оставалась централизованной

---

## Поведение

## 1. Default
По умолчанию:

- `''` остаётся `''`

Никакой магии без явной настройки.

## 2. Если правило включено и значение = `''`
Тогда:

- значение нормализуется в `null`

## 3. Если правило `NullIfBlank`
Тогда:

- `''` -> `null`
- `'   '` -> `null`

Рекомендация:
- нормализовать через `trim($value) === ''`

## 4. Non-nullable property
Если `'' -> null` включено, а поле non-nullable:

### Варианты
1. дождаться `TypeError`
2. бросать раннюю `ConfigurationException`

### Рекомендация
Выбрать **раннюю `ConfigurationException`**.

Почему:
- понятнее для пользователя
- лучше объясняет, что normalization policy конфликтует с declared type

## 5. Nullable property
Если поле nullable, то это основной supported кейс.

Например:

```php
#[EmptyStringAsNull]
public ?string $name;
```

При `''` получаем:
- `null`

## 6. Missing не трогаем
Важно:

- `Missing` остаётся `Missing`
- пустая строка не должна менять semantics `ValueState`
- это отдельная normalization layer, а не переопределение `ValueState`

То есть:
- `Missing` и `Null` по-прежнему отдельные состояния
- normalization только преобразует raw value до hydration

---

## Границы

## Входит в scope
- новый hydration attribute
- новое hydration policy поле/enum
- raw-input normalization в `Hydrator`
- тесты
- docs по DTO hydration

## Не входит
- transport body serialization
- `DtoSerializer`
- `RequestPartsCollector`
- request-level normalization
- `DefaultValue` semantics
- `ClientConfig`

Это именно DTO hydration feature.

---

## Почему не через `DefaultValue`

`DefaultValue` по смыслу сейчас привязан к:

- `Missing`
- `Null`

Пустая строка `''` — это отдельный raw input case.

Если перегрузить `DefaultValue` этой ответственностью:
- semantics станут менее ясными
- смешаются transport/raw normalization и value-state rules

Поэтому:
- `DefaultValue` не трогаем
- делаем отдельный explicit normalization layer

---

## Риски

## 1. Случайно изменить default contract ядра
### Почему
Если normalization начнёт работать без явной настройки, это будет breaking change.

### Меры
- default всегда `Keep`
- все изменения только opt-in

## 2. Конфликт с non-nullable полями
### Почему
`'' -> null` для `string` создаёт несовместимость с declared type.

### Меры
- ранняя `ConfigurationException`
- явный тест
- явная документация

## 3. Пересечение с `Cast`
### Почему
Если сначала сделать normalization, потом cast, или наоборот, поведение может различаться.

### Меры
- зафиксировать приоритет `Cast` выше normalization
- не применять `EmptyStringAsNull`, если задан `#[Cast]`

## 4. Неочевидность для SDK authors
### Почему
Они могут подумать, что `''` теперь глобально всегда `null`.

### Меры
- явно указать, что это opt-in
- в docs вынести рекомендацию, когда использовать property-level атрибут, а когда profile-level policy

---

## Что менять в коде

## 1. Новый enum
- `src/Enums/DataTransfer/EmptyStringBehavior.php`

## 2. Новый attribute
- `src/Attributes/DataTransfer/EmptyStringAsNull.php`

Минимальные поля:
- `public bool $blank = false`

И helper:
- `matches(string $value): bool`

## 3. `DtoHydrationPolicy`
Добавить:
- `EmptyStringBehavior $emptyStringBehavior = EmptyStringBehavior::Keep`

И обновить merge logic.

## 4. `DtoHydrate`
Добавить возможность partial override:
- `?EmptyStringBehavior $emptyStringBehavior = null`

## 5. `Hydrator`
На этапе после `resolveValueWithFallbacks(...)` добавить normalization step:

- если `state` не `Present` -> ничего не делаем
- если `value` не `string` -> ничего не делаем
- если есть `#[Cast]` -> ничего не делаем
- иначе:
  - field-level `EmptyStringAsNull`
  - или profile-level `EmptyStringBehavior`

Если normalization вернула `null`:
- обновить локальный `$value`
- перевести `$state` в `ValueState::Null`

Это важно, чтобы дальше `DefaultValue(... when: [Null])` продолжал работать корректно.

---

## Тестовый план

## 1. Property-level
- `#[EmptyStringAsNull]`:
  - `'' -> null`
  - `'abc' -> 'abc'`

## 2. `blank: true`
- `'   ' -> null`
- `' x ' -> ' x '`

## 3. Profile-level
- `DtoHydrationProfile` с `NullIfEmpty`
- DTO без property-level override
- `'' -> null`

## 4. Priority
- есть `#[Cast]` + `#[EmptyStringAsNull]`
- работает `Cast`, normalization не вмешивается

## 5. Non-nullable
- поле `string`
- `'' -> null` policy включена
- ожидать `ConfigurationException`

## 6. DefaultValue interplay
- `#[DefaultValue(... when: [Null])]`
- `'' -> null`
- default применяется как для `Null`

Это один из самых важных кейсов.

## 7. Inherited property
- non-constructor inherited nullable property
- `'' -> null`
- должно работать и в новом property-fill fallback path

---

## Документация

Обновить:
- `docs/guides/dto.md`
- `docs/guides/attributes/data-transfer.md`
- `docs/glossary/dto.md`
- `docs/guides/provider-methodology.md`
- `docs/guides/provider-checklist.md`

### Что объяснить
- default = `Keep`
- property-level attribute
- hydration profile-level policy
- как выбирать между ними
- что `DefaultValue` не заменяет эту feature

---

## DX после реализации

## Точечно
```php
final readonly class PersonDto extends AbstractDto
{
    public function __construct(
        #[EmptyStringAsNull]
        public ?string $middleName = null,
    ) {}
}
```

## Централизованно
```php
final readonly class ProviderDtoHydrationProfile implements DtoHydrationProfileInterface
{
    public function policy(): DtoHydrationPolicy
    {
        return new DtoHydrationPolicy(
            namingStrategy: NamingStrategy::SnakeCase,
            emptyStringBehavior: EmptyStringBehavior::NullIfEmpty,
        );
    }

    public function casts(): array
    {
        return [];
    }
}
```

## Локальный DTO override
```php
#[DtoHydrate(emptyStringBehavior: EmptyStringBehavior::NullIfBlank)]
final readonly class ProviderPersonDto extends BaseDto
{
    public function __construct(
        public ?string $comment = null,
    ) {}
}
```

---

## Definition of Done

Задача считается завершённой, если:

1. empty string normalization работает как opt-in feature
2. default поведение ядра не меняется
3. property-level и profile-level режимы работают
4. `DefaultValue(... when: [Null])` совместим с этой нормализацией
5. non-nullable conflict даёт понятную ошибку
6. docs явно описывают новый контракт

