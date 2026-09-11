# План работ: DTO Fallback/Alias + Default/Optional
Дата: 2026-02-05  
Контекст: `packages/brahmic/apisutra`

## Цели
1. Добавить **fallback/alias** для чтения значений из нескольких ключей ответа.
2. Добавить **default** семантику для отсутствующих значений с сохранением PHP‑дефолтов.
3. Не ломать текущий контракт и сохранить обратную совместимость.

## Предлагаемое API

### Fallback/Alias
Расширить `#[From]` (и `#[Nested]`) опцией `fallback`:
```php
#[From('full_name', fallback: ['fullName', 'fio'])]
public string $name;

#[Nested(type: AddressDto::class, from: 'address', fallback: ['addr', 'location.address'])]
public ?AddressDto $address;
```

### Default
Добавить атрибут для значений по умолчанию:
```php
#[DefaultValue(value: 'unknown', when: [ValueState::Missing, ValueState::Null])]
public string $status;
```

Для сложных дефолтов — опциональный provider:
```php
#[DefaultValue(provider: StatusDefaultProvider::class, when: [ValueState::Missing, ValueState::Null])]
public string $status;
```

## Область изменений
1. **Attributes (DataTransfer)**  
   - Обновить `From` (добавить `fallback: array<int, string> = []`).  
   - Обновить `Nested` (добавить `fallback: array<int, string> = []`).  
   - Добавить `DefaultValue` (новый атрибут).  
   - Добавить `ValueState` enum: `Missing`, `Null`, `Present`.  

2. **Support / ArrayPath**  
   - Добавить метод определения «missing vs null»:
     - `ArrayPath::getByPathWithStatus(array $data, string $path): array{state: ValueState, value: mixed}`

3. **Hydrator**  
   - Использовать `getByPathWithStatus()` для извлечения значения.  
   - Логика приоритетов:
     1) Извлечь значение по `From` или `Nested::from`.  
     2) Если **не найдено**, попробовать `fallback` в указанном порядке.  
    3) Если всё ещё **missing** → **не добавлять** значение (оставить дефолт конструктора).  
    4) Если есть `DefaultValue` и `state` входит в `when` (массив `ValueState`) → применить default.
     5) Затем применить `Nested`/`Cast`.

4. **Контракт дефолта**
   - `DefaultValue` может принимать либо `value`, либо `provider` (class-string).
   - `provider` и `when` должны сосуществовать (when определяет условие вызова provider).
   - Provider реализует интерфейс:
     ```php
     interface DefaultValueProviderInterface {
         public function resolve(
             mixed $value,
             ValueState $state,
             array $source,
             ?PipelineContext $context,
         ): mixed;
     }
     ```

## Тесты
Добавить/обновить тесты гидрации:
1. **From fallback**: первый ключ отсутствует, берётся следующий.
2. **Nested fallback**: вложенный путь меняется, DTO строится корректно.
3. **Default (missing)**: ключ отсутствует → default применён.
4. **Default (null)**: ключ есть, но null → default применён (по режиму).
5. **Constructor default (без атрибутов)**: missing не перетирает дефолт свойства.  
6. **Default + constructor default**: default применяется только при state из `when`.  
7. **Provider default**: сложный дефолт, доступен `context`.

## Документация
1. `docs/glossary/dto.md` (или `docs/glossary/requests.md`) — описать новые атрибуты.
2. Короткие примеры в `docs/glossary/README.md`.

## Риски и ограничения
- Нужен метод отличать missing от null (иначе дефолты будут «слипаться»).
- Нужен явный порядок: missing → дефолт конструктора; null → только `DefaultValue` при `when`.
- Введение `fallback` в `Nested` важно для консистентности, иначе alias не работает для вложенных DTO.

## Порядок выполнения
1. Добавить `ArrayPath::getByPathWithStatus()`.
2. Расширить `From` и `Nested`.
3. Добавить `DefaultValue`, `ValueState`, `DefaultValueProviderInterface`.
4. Обновить `Hydrator` с новой логикой извлечения.
5. Добавить/обновить тесты.
6. Обновить глоссарий.
