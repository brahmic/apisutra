# Контракт AS-6: отдельные хранилище и параметры

Основание — [план 033](pln-033-readme.md). **Принят 2026-09-15** по
[K03](questions.md#k03-разделение-api-и-миграция) и
[ADR-004](../../adr/adr-004-cache-store-separation.md).
Этот контракт заменяет прежнюю таблицу разрешения cache/cacheConfig. Реализация завершена; [отчёт и проверки](implementation.md).

## Публичные объявления

У ClientConfig остаются два независимых поля и одноимённых аргумента конструктора:

```php
public ?CacheInterface $cacheStore = null,
public ?CacheConfig $cacheConfig = null,
```

Это фрагмент параметров конструктора с promotion, не новый конструктор целиком.
Импорт CacheInterface — `Psr\SimpleCache\CacheInterface`. Аргумент cacheStore занимает
прежнее место cache; порядок остальных аргументов ClientConfig не меняется. Оба поля
readonly, как остальная конфигурация. Аргумент/поле cache удаляются; store не принимает
CacheConfig. Допустимо сохранить явное объявление полей вместо promotion.

Новая сигнатура CacheConfig, без аргумента и свойства store:

```php
public function __construct(
    public int $ttl = 3600,
    public string $prefix = '',
    public CacheMode $mode = CacheMode::Enabled,
    public ?CacheIdentityProviderInterface $identity = null,
    public ?AuthLockProviderInterface $locks = null,
)
```

CacheConfig остаётся final readonly. Namespace классов, типы и defaults параметров
не меняются. identity задаёт контекст подключения для HTTP/auth; locks — provider
блокировок auth. Они сохраняют текущие области действия, а не становятся только
HTTP-параметрами. Отдельные блоки auth/http cache и новый ConfigBuilder не вводятся.

Старых алиасов, magic properties, legacy-режима, двух источников store или скрытого
слияния нет. Переход выполняется вместе с обновлением версии по
[миграции](migration.md). Это осознанное изменение совместимости.

## Сборка и копирование

`S` — PSR-16 store, `P` — экземпляр CacheConfig с ttl/prefix/mode/identity/locks.
В конструкторе отсутствие аргумента совпадает с null. В `with(mixed ...$overrides)`
отсутствие ключа означает сохранить поле, явный null — установить именно null.

| Аргументы конструктора | public cacheStore | public cacheConfig | Поведение |
| --- | --- | --- | --- |
| Оба отсутствуют/null | null | null | Общего store нет |
| Только cacheStore=S | Тот же S | null | Store и штатные параметры по умолчанию |
| Только cacheConfig=P | null | Тот же P | Настройки доступны, общего store нет |
| cacheStore=S, cacheConfig=P | Тот же S | Тот же P | Независимые источник и настройки |

| Явные overrides в with | Результат |
| --- | --- |
| Нет, либо только timeout/иной несвязанный параметр | Новый ClientConfig, оба поля и ссылки сохранены |
| cacheStore=S2 | Только store меняется на S2; исходный P по ссылке |
| cacheConfig=P2 | Только настройки меняются на P2 по ссылке; исходный S сохранён |
| cacheStore=S2, cacheConfig=P2 | Оба явно переданных значения применяются |
| cacheStore=null | Подключение общего store убрано; P сохранён |
| cacheConfig=null | S сохранён; поле настроек null, действуют штатные defaults |
| cacheStore=null, cacheConfig=P2 | Нет общего store; новые настройки P2 сохранены |
| cacheStore=S2, cacheConfig=null | Новый S2 и штатные defaults |
| Оба null | Оба поля null; убраны подключение и настройки |

Замена настроек — целиком. Новый `CacheConfig(ttl: 10)` не наследует старые prefix,
mode, identity или locks. `with(cacheConfig: null)` после Disabled возвращает
Enabled по умолчанию при наличии store. Это изменение параметров, не отключение.
Публичное поле cacheConfig при null не заполняется материализованным объектом defaults.

Повторное with() и последовательность изменений следуют той же таблице. Изменение
store и изменение параметров независимы: перестановка этих двух операций сохраняет
одинаковую пару ссылок. Подключение после cacheStore=null не восстанавливается ни
заменой cacheConfig, ни параметрами запроса; для него требуется новый cacheStore.

Сохранение обычных полей with, включая явный null, не меняется. Общая переработка
остальных параметров ClientConfig не входит в план. Store, identity, locks и блок P
не клонируются; при сборке/копировании их методы не вызываются. Исходная конфигурация
и клиент не перенастраиваются, записи store не удаляются.

## Применение в HTTP и auth

Единственный источник общего store — `$config->cacheStore`. Чтений прежнего cache,
fallback к CacheConfig.store и создания CacheConfig со store в исполняемом коде нет.

- CacheManager получает store отдельно, разрешает параметры с прежними defaults,
  атрибутами и runtime overrides. При null store HTTP-кеш и его invalidation
  не выполняют обращений к общему backend, даже при явном Enabled у запроса.
- AuthBindingResolver и AuthHandler используют тот же cacheStore при соблюдении
  прежних условий общей identity; отсутствие identity по-прежнему может выбрать
  локальную область. Наличие store само по себе не отменяет эти условия.
- AbstractClient передаёт cacheStore существующим auth-обработчикам там, где раньше
  выбирал из пары. Отдельный код клиента или Laravel не подставляет запасной store.
- CacheExecutionState продолжает хранить выбранный store конкретного выполнения.
  Это ссылка для чтения/записи/поколений, не второй источник конфигурации.
- CacheIdentityResolver принимает параметры без store; имена ключей, scopes,
  алгоритм identity и формат кешируемого HTTP-ответа не меняются.

| Намерение | Средство |
| --- | --- |
| Убрать общий store у новой конфигурации | with(cacheStore: null); настройки остаются |
| Убрать также параметры identity/locks/prefix/mode | with(cacheStore: null, cacheConfig: null) |
| Отключить HTTP-кеш по умолчанию, сохранив auth store | cacheConfig с mode Disabled; прежние явные overrides могут разрешить HTTP |
| Отключить HTTP-кеш одного выполнения | withoutCache(); auth store остаётся |
| Инвалидировать уже сохранённые ответы | Прежние clearCache/clearScope через клиентский API |

cacheStore=null не означает запрет любых внешних хранилищ приложения. Сохранённый
явный cacheConfig.locks продолжает действовать: он может иметь собственный backend.
Без явного locks provider прежний выбор — provider из store, если он реализует
AuthLockProviderInterface, иначе локальный. Для сброса и store, и явного locks
нужны оба null либо новые параметры с locks=null. Свой store пользовательского
auth-расширения и RateLimitConfig.store не перенастраиваются этим правилом.

Копирование никогда не очищает общий backend. Новый клиент без общего store сохраняет
штатный локальный auth-путь, а исходный клиент продолжает работать со своим store.
Уже выданный токен автоматически не переносится между новыми экземплярами.

## Неподдержанный старый API и ошибки

Новая типизация делает прежний конфликт источников невыразимым в корректном вызове.
Нет таблицы приоритетов двух store и нет специального режима их согласования.

| Вызов старого/неверного API | Целевая граница |
| --- | --- |
| new ClientConfig(cache: ...) | PHP Error: неизвестный именованный аргумент, до создания клиента/HTTP |
| with(cache: ...), также вместе с cacheStore | То же отклонение неизвестного аргумента при передаче overrides конструктору; значение не игнорируется |
| new CacheConfig(store: ...) | PHP Error: неизвестный именованный аргумент |
| CacheConfig в cacheStore, store вместо cacheConfig | PHP TypeError по новой сигнатуре |
| Чтение прежних публичных свойств cache / CacheConfig.store | Свойств нет; требуется миграция, magic-совместимость не предоставляется |

Текст сообщений движка не является контрактом пакета. Новых пользовательских
значений в лог диагностики не добавлять. Прежние ConfigurationException для
недопустимых операций HTTP-кеша, ошибки самого PSR-16 backend и режимы результата
не меняются. Решения K01/K02 для старой пары заменены K03; сохраняется намерение
явного отказа вместо скрытого выбора, но не специальная категория старого конфликта.

## Стоимость и совместимость исполнения

Копирование — независимые присваивания ссылок и readonly-значений, без сериализации,
клонирования, сети, новых контейнеров и registry. Нет нового общего resolver-а.
Defaults/атрибуты CacheManager разрешают только параметры, не переносят store в P.

При одинаковых S/P перенесённый SDK сохраняет TTL, ключи, prefix/identity,
ограничения файлов/готовых URL и invalidation. Сам переход API не меняет формат
записей store и не требует его очистки. Замена TTL действует на новые записи;
срок существующих записей сам по себе не переписывается.
