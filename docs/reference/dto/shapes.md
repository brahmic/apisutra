# Вложенные объекты и списки

## Вложенные DTO
Когда ответ содержит вложенные объекты или списки объектов (например, `user.address`, `order.items[]`).

Одиночный объект может быть plain DTO без базового класса ApiSutra:

```php
<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Serialization\Hydrator;

final readonly class AddressDto
{
    public function __construct(public string $city)
    {
    }
}

final readonly class UserDto
{
    #[Nested(type: AddressDto::class)]
    public AddressDto $address;
}

$payload = json_decode('{"address":{"city":"Sample"}}', true, flags: JSON_THROW_ON_ERROR);
$user = Hydrator::default()->hydrate($payload, UserDto::class);
echo $user->address->city; // Sample
```

То же объявление работает с constructor promotion и через `Returns`.
Для одиночного свойства конкретный класс можно вывести из native-типа:
`#[Nested] public AddressDto $address`. Для массива `type` задаёт класс элемента.
Правила выбора объекта/списка, nullable/union и все параметры —
в [справочнике Nested](../attributes/hydration.md#nested).

Пример key‑mode (кейс вида `{"person": {...}}`):
```php
use Brahmic\ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use Brahmic\ApiSutra\Enums\DataTransfer\NestedUnknownVariant;

#[Nested(
    discriminatorMode: NestedDiscriminatorMode::Key,
    map: [
        'person' => PersonOwnerDto::class,
        'organization' => OrganizationOwnerDto::class,
    ],
    unknownVariant: NestedUnknownVariant::KeepRaw,
)]
public array $owners = [];
```

Если nested-массив уже найден правильно, но каждый его элемент нужно отдельно преобразовать, используйте `itemCast`:

```php
use Brahmic\ApiSutra\Casts\DataUriBase64FileCast;
use Brahmic\ApiSutra\VO\Files\Base64File;

#[Nested(
    type: Base64File::class,
    itemCast: DataUriBase64FileCast::class,
)]
public array $faces = [];
```

Правило:
- `Cast` на свойстве — transform всего значения свойства
- `Nested(itemCast: ...)` — transform каждого элемента массива

## Формы, присутствие и defaults

`ValueShape` предоставляет `int()`, `float()`, `bool()`, `true()`, `false()`, `string()`,
`mixed()`, `scalars(ScalarType ...$types)`, `nullable(ValueShape $shape)`, `dto(string $class)`,
`list(ValueShape $item, ?string $each = null, ?HandlerSpec $itemCast = null, bool $normalizeKeys = false)`.
Списки могут быть вложенными. PHPDoc `list<int>` и bare `array` сами элементы не проверяют.
Plain native-класс без `dto()`, Nested или DtoInterface не гидратируется автоматически.

`list()` требует плотные ключи 0..n−1; словарь и разреженный массив дают
`invalid_list_shape`. `normalizeKeys: true` разрешает словарь и переиндексирует результат,
сохраняя исходные ключи в диагностике и extras. Traversable не считается списком.
`dto()` допускает объект или массив формы object; непустой list даёт `invalid_object_shape`.
Пустой PHP-массив допустим в обеих формах: после assoc-декодирования различие `{}`/`[]`
и `{"0":...}`/`[...]` восстановить нельзя.

Присутствие, исходный null, нормализация и default проверяются по
[порядку обработки поля](defaults.md#внешние-правила-поля).

В списке порядок — each → itemCast → форма элемента. Готовый DTO от itemCast или provider
не гидратируется повторно; `cast(..., result: ...)` проверяет готовое значение.
У атрибутного Nested cast всего поля по-прежнему игнорируется.

## Nested: одиночный объект и коллекция

Гидратор выбирает назначение по объявлению свойства, до разбора его данных:

| Объявление | Обработка |
| --- | --- |
| `#[Nested] AddressDto` или `?AddressDto` | Одиночный DTO указанного класса |
| `#[Nested(type: AddressDto::class)] AddressDto` | Одиночный DTO; допустим также конкретный подтип |
| Интерфейс или абстрактный класс свойства | Для одиночного объекта нужен совместимый конкретный `Nested.type` |
| `object` | Для одиночного объекта нужен конкретный `Nested.type` |
| `array`, `iterable`, `mixed`, отсутствие native-типа | Сохраняется обработка массива элементов; `type` задаёт класс элемента |
| Класс `Traversable`, включая `AbstractCollection` / `AbstractTypedCollection` | Коллекция; `type` задаёт класс элемента, `map` — классы вариантов |
| Пользовательская обёртка и отдельный, несовместимый с ней `type` элемента | Коллекция, если есть вызываемая `fromArray()` либо публичный конструктор, принимающий массив первым аргументом без других обязательных аргументов |
| Фабричная обёртка с `map` без `type` | Коллекция через `fromArray()` |
| Union только классов/интерфейсов | Для одиночного объекта обязателен `type`, совместимый хотя бы с одной веткой |

`null` не выбирает ветку union. Union с разной кардинальностью, например
`AddressDto|array`, union без необходимого `type`, несовместимый класс, недоступный
конструктор или intersection дают `ConfigurationException`. Параметры списка
`each`, `itemCast`, `discriminator`, `map` несовместимы с одиночным объектом.
Эта проверка выполняется при обработке ненулевого найденного значения.

Одиночный DTO принимает ассоциативный массив или PHP-объект. Гидратация проходит
через обычные проверки полей и один вызов конструктора, включая готовый PHP DTO.
Scalar и непустой PHP list дают `unexpected_response_shape` по пути свойства.
Пустой массив проходит проверку полей дочернего DTO: например, отсутствие `city`
даёт `required_field_missing` с путём `address.city`.
После JSON decode с `assoc=true` пустые `{}` и `[]` неразличимы; `Nested` это
различие не восстанавливает. [Исполняемый пример](shapes.md#вложенные-dto).

`Nested.from` переопределяет путь `From` / `Map` / имени свойства.
Непустой `Nested.fallback` имеет приоритет над `From.fallback`; fallback применяется
только при отсутствии ключа, а найденный null сохраняется. `DefaultValue`,
constructor default, nullable и пустая typed collection сохраняют
[общие правила missing/null](defaults.md#обязательные-поля-и-ошибки-гидратации).

Для списков порядок остаётся `each` → `itemCast` → гидратация элементов или
discriminator → обёртка коллекции. `Nested` не включает проверку PHPDoc `list<T>`;
форма списка и scalar-элементы требуют явных проверок. Ошибка элемента содержит
его порядковый индекс. `#[Cast]` всего свойства при наличии `Nested` не выполняется.

Миграция: корректный одиночный объект из JSON теперь гидратируется, ошибочный
получает путь дочернего поля без фиктивного `[0]` и вызова DTO как обёртки списка.
Для неоднозначных деклараций задайте конкретный класс и определите в native-типе,
содержит свойство объект или коллекцию.
