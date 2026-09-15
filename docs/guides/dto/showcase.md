# Возможности DTO на одном примере

`CatalogItemDto` описывает товар вымышленного каталога. На одной модели показаны
переименование полей, defaults, преобразования, вложенность, коллекции и исходящий JSON.
Начните с таблицы результатов или прочитайте пример сверху вниз; все декларации ниже исполняются.

[Входной JSON](#входной-json) · [DTO](#декларация-dto) · [Правила](#правила-клиента) ·
[Результат](#что-получит-приложение) · [Отправка](#что-уйдёт-в-запрос) · [Ошибки](#как-выглядят-ошибки) · [Другие варианты](#как-выбрать-другой-приём).

Из checkout после `composer install`:

```bash
php docs/example/dto-showcase/run.php
```

В установленном пакете добавьте к пути `vendor/brahmic/apisutra/`.
[Запуск](../../example/dto-showcase/run.php) использует локальный MockTransport без сети;
[ожидаемый результат](../../example/dto-showcase/fixtures/expected.json) содержит DTO, DX, HTTP payload и ошибки.

## Входной JSON

Это [содержимое поля `data`](../../example/dto-showcase/fixtures/item.json) успешного ответа API.
В нём намеренно нет `displayName` и `stock`, а `title` задан как null.

```json
{
  "product_id": 7,
  "vendor_code": "BK-7",
  "title": null,
  "description": "   ",
  "available": true,
  "metrics": {"rating": 4.8, "votes": 12},
  "created_at": "2026-09-15T10:30:00+00:00",
  "state": "active",
  "price": "12.34",
  "seller": {"id": 9, "name": "Книжная лавка", "tier": "gold"},
  "tags": [{"name": "php"}, {"name": "sdk"}],
  "related_ids": [11, 12],
  "assets": [
    {"value": {"type": "image", "url": "https://assets.example.test/cover.png", "width": 640}, "rank": 1},
    {"value": {"type": "video", "url": "https://assets.example.test/demo.mp4", "duration": 30}, "rank": 2}
  ],
  "future_flag": false
}
```

## Декларация DTO

Полный [CatalogItemDto](../../example/dto-showcase/src/CatalogItemDto.php). База `AbstractDto`
даёт `from()`, `toArray()` и `with()` и подходит для `Returns`. `AbstractResponseDto`
дополнительно предоставляет `computed()`; [обычные PHP-классы](plain-models.md) также поддерживаются.

```php
declare(strict_types=1);

namespace Example\DtoShowcase;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use Brahmic\ApiSutra\Attributes\DataTransfer\DateTimeTo;
use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Attributes\DataTransfer\DtoSerialize;
use Brahmic\ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\Map;
use Brahmic\ApiSutra\Attributes\DataTransfer\Nested;
use Brahmic\ApiSutra\Attributes\DataTransfer\To;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\Enums\Serialization\EnumOutput;
use DateTimeImmutable;

// toArray() сохраняет null и строковые значения enum.
#[DtoSerialize(enumOutput: EnumOutput::Value, serializeNulls: true)]
final readonly class CatalogItemDto extends AbstractDto
{
    /**
     * @param list<int> $relatedIds
     * @param list<ImageDto|VideoDto> $media
     * @param array<string, mixed> $_extra
     */
    public function __construct(
        // Входное имя с запасным путём; исходящее имя задаётся отдельно.
        #[From('product_id', fallback: ['id'])]
        #[To('product_id')]
        public int $id,
        // Одно внешнее имя для чтения и записи.
        #[Map('vendor_code')]
        public string $sku,
        // Отсутствие и явный null разрешены контрактом вымышленного API.
        #[DefaultValue('Без названия', when: [ValueState::Missing, ValueState::Null])]
        public string $title,
        // Ключ обязателен, но его значение может быть null или пустой строкой.
        #[EmptyStringAsNull(blank: true)]
        public ?string $description,
        // Набор правил ниже проверяет точный тип bool.
        public bool $available,
        // Вложенный путь можно развернуть в отдельное свойство DTO.
        #[From('metrics.rating')]
        public float $rating,
        // Вход содержит время с часовым поясом, исходящий формат — календарную дату.
        #[From('created_at')]
        #[To('created_at')]
        #[DateTimeFrom(format: DATE_ATOM, strictFormat: true)]
        #[DateTimeTo(format: 'Y-m-d', timezone: 'UTC')]
        public DateTimeImmutable $createdAt,
        // Строковое значение API превращается в backed enum.
        #[From('state')]
        #[To('state')]
        public ItemStatus $status,
        // Собственный cast читает "12.34" как 1234 и выполняет обратное преобразование.
        #[From('price')]
        #[To('price')]
        #[Cast(MinorUnitsCast::class)]
        public int $priceMinor,
        // SellerDto — обычный PHP-класс; Nested создаёт отдельный вложенный объект.
        #[Nested(type: SellerDto::class)]
        public SellerDto $seller,
        // Элементы становятся DTO, контейнер проверяет их тип и даёт first()/count().
        #[Nested(type: TagDto::class)]
        public TagCollection $tags,
        // Элементы массива проверяет ValueShape::list(), а не PHPDoc.
        #[To('related_ids')]
        public array $relatedIds,
        // Типы вариантов и извлечение value объявлены во внешнем наборе.
        #[To('assets')]
        public array $media,
        // Provider вычисляет отсутствующее значение по исходным данным DTO.
        #[DefaultValue(provider: DisplayNameProvider::class)]
        public string $displayName,
        // Отсутствие разрешено; явный null запрещает внешнее правило.
        public ?int $stock = null,
        // Непрочитанные поля; сбор включается через extras('_extra').
        public array $_extra = [],
    ) {
    }
}
```

Вспомогательные типы: [SellerDto](../../example/dto-showcase/src/SellerDto.php) — обычный класс;
[TagDto](../../example/dto-showcase/src/TagDto.php) и [TagCollection](../../example/dto-showcase/src/TagCollection.php) — типизированная коллекция;
[ImageDto](../../example/dto-showcase/src/ImageDto.php) / [VideoDto](../../example/dto-showcase/src/VideoDto.php) — варианты;
[ItemStatus](../../example/dto-showcase/src/ItemStatus.php) — enum.

[MinorUnitsCast](../../example/dto-showcase/src/MinorUnitsCast.php) переводит строковую цену
в целые сотые и обратно. Учебный формат допускает до семи цифр перед точкой и ровно две после неё.
[DisplayNameProvider](../../example/dto-showcase/src/DisplayNameProvider.php) возвращает `Товар BK-7`
по исходному `vendor_code`; provider видит исходные данные, а не уже заполненные свойства DTO.

## Правила клиента

[CatalogRules](../../example/dto-showcase/src/CatalogRules.php) дополняет атрибуты:
строгие типы, проверка элементов массива, `each`, discriminator, запрет явного null и extras.

```php
declare(strict_types=1);

namespace Example\DtoShowcase;

use Brahmic\ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\FieldRule;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Brahmic\ApiSutra\Serialization\Rules\ValueShape;

final class CatalogRules
{
    public static function create(): HydrationRules
    {
        return HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
            ->withDto(CatalogItemDto::class, DtoRules::create()
                ->field('relatedIds', FieldRule::create()->from('related_ids')->required()
                    ->shape(ValueShape::list(ValueShape::int())))
                ->field('media', FieldRule::create()->from('assets')->required()
                    ->shape(ValueShape::list(
                        ValueShape::variants('type', [
                            'image' => ImageDto::class,
                            'video' => VideoDto::class,
                        ], unknown: NestedUnknownVariant::Error),
                        each: 'value',
                    )))
                ->field('stock', FieldRule::create()->forbidExplicitNull())
                ->extras('_extra'))
            ->withDto(SellerDto::class, DtoRules::create()->extras('_extra'));
    }
}
```

`FieldRule` применяется к полям `relatedIds`, `media` и `stock`; их входное поведение
не дублируется атрибутами. Исходящий `To` на них разрешён. Попытка добавить
`field('id', ...)` к уже объявленному `From` даст `ConfigurationException`.
[Правила конфликтов](../../reference/dto/field-rules.md#правила-и-проверка-конфигурации).

В [run.php](../../example/dto-showcase/run.php) JSON загружается в массив `$source`,
а набор создаётся через `$rules = CatalogRules::create()`. Самостоятельная гидратация:
`Hydrator::forRules($rules)->hydrate($source, CatalogItemDto::class)`.

Тот же `$rules` передаётся в `ClientConfig(hydrationRules: $rules, ...)`.
[GetCatalogItemRequest](../../example/dto-showcase/src/GetCatalogItemRequest.php) использует
`Returns(CatalogItemDto::class, unwrap: 'data')`. `CatalogItemDto::from()` само по себе
набор клиента не получает: для этого примера нужен `Hydrator::forRules()` или клиент с набором.

## Что получит приложение

| Свойство | Вход / условие | Механизм → результат |
| --- | --- | --- |
| `id` | `product_id: 7` | From → `7`; при отсутствии ключа проверяется `id`, найденный null не включает fallback |
| `sku` | `vendor_code: "BK-7"` | Map → `"BK-7"`, то же внешнее имя используется при записи |
| `title` | null или ключ отсутствует | DefaultValue → `"Без названия"` |
| `description` | `"   "` | EmptyStringAsNull → null; обычный текст сохраняется; отсутствие ключа — ошибка |
| `available` | true | Strict → bool; строка `"true"` не заменяет bool |
| `rating` | `metrics.rating: 4.8` | From с вложенным путём → float; соседний votes остаётся в `_extra` |
| `createdAt` | строка с датой и смещением | DateTimeFrom → DateTimeImmutable, дата остаётся объектом до сериализации |
| `status` | `state: "active"` | Native enum → `ItemStatus::Active` |
| `priceMinor` | `price: "12.34"` | Cast → `1234`; при сериализации снова `"12.34"` |
| `seller` | объект с id/name/tier | Nested → SellerDto; непрочитанный tier сохраняется в `seller->_extra` |
| `tags` | два объекта с name | Nested → TagCollection с двумя TagDto; отсутствие поля даёт пустую коллекцию |
| `relatedIds` | `[11, 12]` | ValueShape::list(int) → список int; строка внутри списка даёт ошибку |
| `media` | `assets[*].value` | each + variants → ImageDto и VideoDto; неизвестный type даёт ошибку |
| `displayName` | ключ отсутствует | Provider → `"Товар BK-7"` |
| `stock` | ключ отсутствует / 0 / null | Получится null / 0 / ошибка соответственно |
| `_extra` | metrics.votes, future_flag и rank рядом с value | Остаток данных, не прочитанный полями; не создаёт динамических свойств |

У `_extra` получается такой [остаток](../../reference/dto/extras.md):

```json
{"metrics":{"votes":12},"assets":[{"sourceKey":0,"remainder":{"rank":1}},{"sourceKey":1,"remainder":{"rank":2}}],"future_flag":false}
```

Остаток `seller` принадлежит самому SellerDto. Ключ discriminator `type` здесь прочитан
свойством выбранного ImageDto/VideoDto. Прочитанный null удаляется из остатка;
false сохраняется, если поле никто не прочитал. Имя `_extra` задаётся явно и не зарезервировано.

## Что уйдёт в запрос

[SaveCatalogItemRequest](../../example/dto-showcase/src/SaveCatalogItemRequest.php) передаёт
этот же объект через `BodyRoot`. Реальная сериализация проверяется на записанном запросе MockTransport.

| Значение | `$item->toArray()` — DX | JSON запроса клиента — wire |
| --- | --- | --- |
| `id` / `sku` | product_id / vendor_code | product_id / vendor_code |
| Дата / enum / цена | `"2026-09-15"` / `"active"` / `"12.34"` | Те же значения |
| `description` / `stock` | null сохраняется благодаря DtoSerialize | Ключи опущены стандартной wire-политикой |
| `_extra`, включая seller | Обычное свойство с остатком | Исключено набором клиента на обеих глубинах |
| `media` | Массив объектов под именем assets | Массив без входной обёртки value и без rank |

Входной `each` не восстанавливает обёртку при записи: исходящая форма задаётся отдельно.
`toArray()` не является обещанием побайтового round-trip исходного JSON.
Настройки [DX и wire](../../reference/serialization/dto-output.md) выбираются по контракту API;
исключение receiver зависит от класса и набора, в том числе для DTO, созданного вручную.
[Ограничения непрозрачных casts и представлений](../../reference/serialization/receiver-output.md).

## Как выглядят ошибки

`run.php` отдельно гидратирует девять повреждённых вариантов. `path` указывает свойство DTO,
`sourcePath` — JSON Pointer во входе standalone; при Returns внешний unwrap добавляет `/data`.

| Нарушение | reason | path | sourcePath |
| --- | --- | --- | --- |
| Нет обоих имён id | required_field_missing | id | /product_id (expected) |
| `product_id: "7"` | invalid_field_type | id | /product_id |
| `product_id: null`, при этом `id: 8` | null_not_allowed | id | /product_id |
| `stock: null` | explicit_null_not_allowed | stock | /stock |
| `related_ids: [11, "12"]` | invalid_field_type | relatedIds[1] | /related_ids/1 |
| Строковый seller.id | invalid_field_type | seller.id | /seller/id |
| Неизвестный type варианта | unknown_nested_variant | media[0] | /assets/0/value |
| Дата не соответствует формату | invalid_datetime | createdAt | /created_at |
| Цена `"12,34"` | invalid_price (свой cast) | priceMinor | /price (boundary) |

Boundary у cast означает известный вход преобразования, а не адрес внутри его результата.
Для `computed()` и непрозрачных преобразований точный источник может быть недоступен.
[Диагностика и безопасный лог](../../reference/dto/diagnostics.md) описывают остальные границы.

## Как выбрать другой приём

| Задача | Вариант и граница |
| --- | --- |
| DTO уже существует и не должен зависеть от ApiSutra | [Обычный PHP-класс + HydrationRules](plain-models.md); примеры форм работают без базового DTO |
| Одинаковые имена, даты и casts во многих моделях | [NamingStrategy и профиль гидратации](../../reference/dto/profiles.md); профиль — альтернатива DtoRules для этого класса |
| Правила вложенного списка удобнее хранить в DTO | [Nested с each/discriminator](../../reference/dto/shapes.md); не совмещать с FieldRule того же свойства |
| Нужны словари, вложенные списки, union или допустимый null элемента | [ValueShape и строгие скаляры](../../reference/dto/scalars.md), [формы](../../reference/dto/shapes.md); PHPDoc сам не валидирует элементы |
| Неизвестные варианты надо сохранять или пропускать | [KeepRaw / Skip](../../reference/dto/variants.md); KeepRaw требует контейнера, допускающего raw-значения |
| Cast/provider сам создаёт вложенные DTO | [HydrationScope](../../reference/dto/scope.md) передаёт текущие правила; простой Hydrator::default() их теряет |
| Default зависит от контекста или нужна проверка найденного значения | [DefaultValue provider и состояния](../../reference/dto/defaults.md#provider-для-найденного-значения); один provider может обработать несколько состояний |
| Нужны прикладные правила и описание смысла поля | [Validate, Label, About](../../reference/attributes/hydration.md); подключение валидатора — [отдельный шаг](../../reference/client/validation.md) |

[Выбрать DTO для своего SDK](../../start/describe-dto.md) · [Полный справочник](../../reference/dto/README.md) · [Исходники и запуск](../../example/dto-showcase/README.md).

Фиксированный `type`, список и словарь, установленные конструктором, показаны
в отдельном [примере constructorValue](../../reference/dto/constructor-values.md).
