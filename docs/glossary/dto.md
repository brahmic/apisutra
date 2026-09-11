# DTO

## DtoInterface
Базовый интерфейс для всех DTO. Метод from(array|object): static для создания из массива или объекта с toArray().

## ResponseDtoInterface
Интерфейс для корневых Response DTO. Extends DtoInterface. Добавляет computed() с nullable context.

## AbstractDto
Базовый абстрактный класс для всех DTO. Implements DtoInterface, ValidatableInterface. Uses ValidatesAttributes trait. Метод from() делегирует к Hydrator::default().
Метод toArray() финализирован и делегирует в DtoSerializer как в канонический DX serialization path.
Методы make()/with() — удобные фабрики для создания и клонирования DTO с переопределениями.

## DtoHydrationProfileInterface
Контракт централизованной DTO hydration policy для SDK/пакета. Отвечает за naming fallback, date-time hydration rules и stable DTO casts для `from()` и pipeline hydration.

## DtoHydrationPolicy
Immutable policy DTO hydration после резолва defaults/override. Используется `Hydrator` как effective hydration contract.

## DtoHydrationProfile (атрибут)
Class-level binding атрибут для привязки DTO или базового DTO к `DtoHydrationProfileInterface`.
Обычно используется на `BaseDto` / `BaseResponseDto`.

## DtoHydrate (атрибут)
Class-level partial override поверх package/profile defaults для hydration semantics.

## DtoSerializationProfileInterface
Контракт централизованной body DTO policy для SDK/пакета. Отвечает за enum output, strictEnums, naming policy, null policy и стабильный набор DTO casts.

## DtoSerializationPolicy
Immutable policy DTO-сериализации после резолва defaults/override. Используется DtoSerializer как effective body DTO contract.

## DtoSerializationProfile (атрибут)
Class-level binding атрибут для привязки DTO или базового DTO к `DtoSerializationProfileInterface`.
Обычно используется на `BaseDto` / `BaseResponseDto`.

## DtoSerialize (атрибут)
Class-level partial override поверх package/profile defaults. Используется редко, когда конкретный DTO должен отклоняться от общего body DTO контракта.

## AbstractResponseDto
Абстрактный класс для корневых Response DTO. Extends AbstractDto, implements ResponseDtoInterface. Метод computed() с nullable context.

## ValidatableInterface
Интерфейс для объектов с валидацией. Методы: validate() (throws ValidationException), isValid() (bool), errors() (array). Реализуется AbstractDto и AbstractRequest.

## CustomValidatableRequestInterface
Расширяемая preflight-валидация запроса до сериализации/HTTP. Метод validateCustom(): array<ValidationError>. Используется для проверок, не покрываемых #[Validate] (файлы, кросс-полевая логика). Ошибки объединяются с attribute-валидацией и передаются в buildValidationFailure. См. docs/guides/validation.md.

## ValidatorInterface
Контракт валидатора. Метод check() возвращает результат валидации. Используется в ValidatesAttributes и Pipeline.

## ValidationResult
Результат валидации. Методы: passed(), failed(), errors() (array<ValidationError>).

## ValidatesAttributes
Trait для унификации валидации. Делегирует к Validator. Используется в AbstractDto и AbstractRequest для DRY.

## Nested (атрибут)
Универсальный атрибут для гидрации свойств DTO. Параметры: type (тип элемента), itemCast (cast для каждого элемента массива), from (путь к данным), fallback (альтернативные пути), each (путь внутри каждого элемента), discriminator (поле для полиморфизма), map (маппинг значение → класс). Все пути поддерживают dot notation.

## From (атрибут)
Атрибут для переименования поля при гидрации DTO. Указывает имя поля в JSON ответе API. Переопределяет NamingStrategy для конкретного свойства. Поддерживает dot notation. Для устойчивости к изменению контрактов можно использовать fallback (список альтернативных путей).

## To (атрибут)
Атрибут для сериализации DTO в body запроса. Указывает имя ключа/пути (dot notation) в выходном payload.

## DefaultValue (атрибут)
Атрибут для задания значения по умолчанию, если поле отсутствует или равно null. Параметры:
- value или provider (используется один из вариантов)
- when (массив ValueState, например Missing/Null)
Если ключ отсутствует и DefaultValue не задан, значение свойства по умолчанию сохраняется.

## DefaultValueProviderInterface
Контракт для вычисления сложных дефолтов. Метод resolve() получает value/state/source/context и возвращает итоговое значение.

## ValueState (enum)
Состояние значения при извлечении из ответа: Missing, Null, Present. Используется в DefaultValue.

## Cast (атрибут)
Атрибут для кастомного преобразования значения при гидрации. Принимает класс, реализующий CastInterface. Используется для дат, enum, кастомных типов.

## DateTimeFrom (атрибут)
Property-level override date-time hydration semantics. Используется для настройки parse format/timezone/strictness без низкоуровневого `Cast`.

## DateTimeTo (атрибут)
Property-level override date-time body serialization semantics. Используется для настройки output format/timezone без низкоуровневого `Cast`.

## EmptyStringAsNull (атрибут)
Property-level hydration normalizer. Позволяет трактовать пустую строку `''` как `null` для конкретного свойства. Может работать и в режиме `blank`, где `null` считаются строки из пробелов.

## CastInterface
Интерфейс для классов-преобразователей. Методы: hydrate (JSON → PHP при гидрации), serialize (PHP → JSON при сериализации). Параметры передаются через конструктор. Глобальная регистрация в ClientConfig::casts по типу.

## CastRegistry
Реестр кастов. Методы: get(type), register(type, cast). Статический метод global() возвращает глобальный singleton для `Dto::from()` без context. Для canonical DTO semantics используются stable profile-level casts; клиентский registry остаётся request/runtime слоем.

## Hydrator
Сервис гидрации JSON → DTO. Методы: hydrate(data, class, ?context), hydrateCollection(items, class, ?context). Статический метод default() возвращает singleton path для `Dto::from()`. Алгоритм: computed() → From/fallback → hydration profile naming → DefaultValue → Cast/Nested → constructor-first instantiation → fallback assignment remaining public properties. Инвариант: `from()` и pipeline hydration должны совпадать.

## DtoSerializer
Сервис сериализации DTO → массив. Учитывает `To`, effective DTO policy, `Cast` и null policy. Используется в `AbstractDto::toArray()` как DX serialization path и также может сериализовать DTO по явной transport policy. После zero-config wire separation `toArray()` и outbound body могут расходиться.

## computed()
Статический метод в DTO для вычисляемых свойств. Сигнатура: `static computed(array $data, ?PipelineContext $ctx = null): array`. Вызывается до создания объекта, результат мержится с данными API. Решает проблему инициализации свойств в readonly DTO.
