# DTO

## DtoInterface
Интерфейс DTO ApiSutra с фабрикой `from(array|object): static`. Plain-классы без
этого интерфейса также поддерживаются через [внешние правила](../guides/hydration-rules.md).

## ResponseDtoInterface
Интерфейс для корневых Response DTO. Extends DtoInterface. Добавляет computed() с nullable context.

## AbstractDto
Необязательный базовый класс DTO. Implements DtoInterface, ValidatableInterface. Uses ValidatesAttributes trait. Метод from() делегирует к Hydrator::default().
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
Атрибут для гидратации одиночного объекта или списка, включая проекции и полиморфные
элементы. Пути поддерживают dot notation. Параметры и границы форм — в
[справочнике атрибутов](../guides/attributes/data-transfer.md).

## From (атрибут)
Атрибут для переименования поля при гидрации DTO. Указывает имя поля в JSON ответе API. Переопределяет NamingStrategy для конкретного свойства. Поддерживает dot notation. Для устойчивости к изменению контрактов можно использовать fallback (список альтернативных путей).

## To (атрибут)
Атрибут для сериализации DTO в body запроса. Указывает имя ключа/пути (dot notation) в выходном payload.

## DefaultValue (атрибут)
Задаёт значение или provider для выбранных состояний `ValueState`; по умолчанию —
Missing. `when: [Null, Present]` позволяет одному provider проверять найденное значение.
Если применимого атрибута нет, действуют defaults объявления DTO.
См. [контракт provider](../guides/attributes/data-transfer.md#provider-для-найденного-значения).

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
Интерфейс преобразователя с методами `hydrate()` и `serialize()`. Регистрация зависит
от направления: `ClientConfig::casts` относится к запросам; входящие casts задаются
атрибутом, профилем или внешним набором. См. [таблицу источников](../guides/casts.md).

## CastRegistry
Реестр casts с `get()` и `register()`. `global()` возвращает общий экземпляр, но
его содержимое не применяется в `Hydrator::default()` / `Dto::from()`.
Клиентский registry используется в сериализации запросов; см. [границы регистрации](../guides/casts.md).

## Hydrator
Сервис гидратации данных в DTO: `hydrate()` и `hydrateCollection()`.
`default()` обслуживает `Dto::from()` без клиентского набора. `forRules($rules)` создаёт
отдельный гидратор с набором и кешем метаданных; одинаковые правила standalone и
клиента требуют явно передать один набор. Алгоритм — в [руководстве](../guides/hydration-rules.md).

## DtoSerializer
Сервис сериализации DTO → массив. Учитывает `To`, effective DTO policy, `Cast` и null policy. Используется в `AbstractDto::toArray()` как DX serialization path и также может сериализовать DTO по явной transport policy. После zero-config wire separation `toArray()` и outbound body могут расходиться.
При переданном наборе исключает объявленный receiver, в том числе у plain DTO;
[исходящие ограничения](../guides/hydration-rules.md#receiver-в-исходящих-запросах) не действуют без набора.

## computed()
Статический метод в DTO для вычисляемых свойств. Сигнатура: `static computed(array $data, ?PipelineContext $ctx = null): array`.
Вызывается до создания объекта; возвращённый массив заменяет вход гидратации.
Нужные исходные поля следует сохранить в этом массиве. При внешних правилах это
граница происхождения данных (`SourcePathKind::Boundary`).

## HydrationRules

Неизменяемый набор входных правил для классов DTO, подключаемый через
`ClientConfig::hydrationRules` или `Hydrator::forRules()`. Полный контракт — в
[руководстве по внешним правилам](../guides/hydration-rules.md).

## DtoRules

Правила одного точного класса: policy, поля и приёмник дополнительных данных.
Рекомендуемое имя приёмника — `_extra`; оно задаётся методом `extras()` и не зарезервировано.

## FieldRule

Правило свойства: пути источника, преобразование, присутствие, null, форма, default
и policy. Конфликтует с входными атрибутами того же свойства.

## ValueShape

Описание скаляра, DTO, списка или nullable-формы. Поддерживает вложенные списки,
проекции и варианты; само объявление PHPDoc `list<T>` таких проверок не включает.

## RulePolicy

Defaults набора, класса или поля для scalar policy, пустой строки, naming и дат.
Casts по типу задаются только на уровне класса или набора.

## ScalarPolicy

Режим Legacy сохраняет прежние scalar conversions; Strict проверяет типы и допускает
точное расширение int → float. См. [таблицу типов](../guides/hydration-rules.md#policy-и-строгие-типы).

## ScalarType

Enum scalar-типов для `ValueShape::scalars()`, включая литералы true/false.

## InputShape

Проверка входной формы Object/List перед преобразованием поля. Не подменяет
проверку присутствия и явного null.

## HandlerSpec

Описание класса cast/provider и аргументов его конструктора. Аргументы допускают
scalar, null, enum и массивы из них; произвольные объекты и closure запрещены.

## DefaultSpec

Внешнее описание default: literal value или provider для выбранных `ValueState`.
Для новых объектов используется provider, а не `DefaultSpec::value()`.

## HydrationScope

Область одного корневого вызова с текущим гидратором и правилами. `hydrate()` и
`hydrateCollection()` сохраняют их в рекурсивных помощниках; `context()` может вернуть
null standalone. Не сохраняйте scope в переиспользуемом обработчике.

## ScopedCastInterface

Расширяет CastInterface методом `hydrateInScope()`. Scope передаётся и обработчикам
из атрибутов, включая `Nested(itemCast:)`; см. [рекурсивные casts](../guides/hydration-rules.md).

## ScopedDefaultValueProviderInterface

Расширяет контракт provider методом `resolveInScope()`, чтобы вложенная гидратация
применяла тот же набор. Обычный `Hydrator::default()` внутри provider его не наследует.

## SourcePathKind

Степень точности исходного пути ошибки: Resolved, Expected, Boundary или Unavailable.
DTO-путь хранится отдельно в `path`; форматы и маскирование — в
[диагностике](../guides/hydration-rules.md#диагностика-и-входы).
