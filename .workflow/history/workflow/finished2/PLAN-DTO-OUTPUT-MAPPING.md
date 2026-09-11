# План работ: DTO Output‑mapping (вариант A)
Дата: 2026-02-05  
Контекст: `packages/brahmic/apisutra`

## Цель
Сделать DTO контрактом **тела запроса (body)** без переноса транспортных деталей в DTO.
Query/Path/Header/File остаются в Request‑атрибутах.

## Принцип
DTO отвечает за структуру и маппинг полей **только для body**.  
Request отвечает за HTTP‑метаданные (method/endpoint/query/path/header/file).
Input‑DTO **опционален**: если body состоит из примитивов/массива, Request можно описывать как сейчас.
DTO **не заменяет** Request: endpoint/method/query/path/header остаются в Request.

## Предлагаемое API

### Новый атрибут
```php
#[To('user_id')]
public int $userId;
```
Назначение: маппинг поля DTO в выходной ключ/путь (dot notation).

### Использование
```php
final readonly class CreateUserRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public CreateUserInput $input,
    ) {}
}

final readonly class CreateUserInput extends AbstractDto
{
    public function __construct(
        #[To('user_id')]
        public int $userId,
        #[To('profile.full_name')]
        public string $fullName,
    ) {}
}
```
```php
// Без input‑DTO — по‑старому
final readonly class SimpleRequest extends AbstractRequest
{
    public function __construct(
        #[Body] public string $subjectId,
        #[Body] public ?string $profile = null,
    ) {}
}
```

## Область изменений

1. **Attributes (DataTransfer)**
   - Добавить `Attributes/DataTransfer/To.php` (TARGET_PROPERTY).

2. **DtoSerializer**
   - Новый сервис `Serialization/DtoSerializer.php`.
   - Логика:
     - `To` → ключ/путь
     - `NamingStrategy` (fallback при отсутствии `To`)
     - `Cast` (использовать `serialize()`)
     - Вложенные DTO и коллекции DTO — рекурсивно
     - Учитывать `ClientConfig::serializeNulls`
     - Null‑значения:
       - если `serializeNulls=false`, исключать null‑поля из body DTO
       - если `serializeNulls=true`, включать null‑поля
     - Использовать `ArrayPath::setByPath()` для dot‑notation
     - Кешировать метаданные DTO (аналогично Hydrator/Serializer)

3. **AbstractDto**
   - Обновить `toArray()` (если есть) или добавить `toArray()` в `AbstractDto`,
     чтобы делегировать в `DtoSerializer`.
   - Сделать `toArray()` **final** для консистентности (breaking change допустим).
   - `DtoSerializer::default()` может использовать `CastRegistry::global()` (аналог Hydrator::default()).

4. **Serializer**
   - В `serializeValue()`:
     - если значение — `DtoInterface`, использовать `DtoSerializer`.
     - иначе поведение без изменений (примитивы/массивы как сейчас).
   - Если DTO имеет кастомный `toArray()`:
     - допускается override, но рекомендация — делегировать в `DtoSerializer`.

5. **Документация**
   - `docs/glossary/dto.md`: описать `To`.
   - `docs/glossary/requests.md`: отметить, что DTO может быть body‑контрактом.

## Breaking changes
1. DTO, передаваемые в body, будут сериализоваться с учётом `To` и `Cast`.
2. `toArray()` DTO будет более формализован (если добавим в AbstractDto).
3. Для body‑DTO с `serializeNulls=false` null‑поля могут исчезать из payload.
4. DTO с кастомным `toArray()` должны быть адаптированы под `To`/`Cast` (если нужен новый формат).
5. `toArray()` в `AbstractDto` становится final (нельзя переопределять).

## Тесты
1. **DTO → body mapping**
   - `To` маппит ключи, включая dot‑notation.
2. **Cast serialize**
   - `Cast` используется при сериализации DTO.
3. **Nested DTO**
   - Вложенные DTO сериализуются рекурсивно.
4. **Null policy**
   - `serializeNulls=true/false` влияет на итоговый body.
5. **Request body integration**
   - Request с `#[Body]` и DTO корректно формирует payload.
6. **Request без DTO**
   - Примитивные `#[Body]` поля работают как раньше.

## Риски
- Дублирование логики с `Serializer` (нужно аккуратно разделить ответственности).
- Нужна чёткая политика null‑значений для DTO.
- Возможные расхождения между `Hydrator` и `DtoSerializer` в NamingStrategy.

## Финальные ожидания
Что получим после внедрения:
1. **DTO как контракт body** — можно передавать DTO в `#[Body]` и получать корректный payload.
2. **Декларативный output‑маппинг** — `#[To]` управляет ключами/путями в body без ручной сборки массивов.
3. **Единый стиль** — `Cast` применяется и при гидрации, и при сериализации.
4. **Чистая архитектура** — транспортные детали остаются в Request, DTO отвечает за данные.
5. **Совместимость с текущими запросами** — примитивные `#[Body]` поля не требуют DTO и работают как раньше.

### Ключевые юзкейсы
1. **Создание/обновление сущностей**
   - `CreateUserInput`, `UpdateUserInput` как DTO‑контракты тела запроса (опционально).
2. **Сложные вложенные структуры**
   - DTO с `#[To('profile.name')]`, `#[To('address.city')]` без ручной сборки.
3. **Единообразная сериализация**
   - Enums/DateTime/Base64/кастомные типы сериализуются через `Cast`.
4. **Переиспользование DTO**
   - Один DTO можно использовать в разных запросах с одинаковым body‑форматом.
5. **Простые запросы**
   - Оставить `#[Body]` на примитивах без ввода новых DTO.

## Порядок выполнения
1. Добавить `To` атрибут.
2. Реализовать `DtoSerializer` + `default()`.
3. Подключить в `AbstractDto` и `Serializer`.
4. Тесты.
5. Документация.
