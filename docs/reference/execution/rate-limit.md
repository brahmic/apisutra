# Квоты запросов

По умолчанию ограничения выключены (`ClientConfig::rateLimit = null`). Для локальной
общей квоты достаточно одной настройки; Redis, Laravel и префиксы не нужны:

```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RateLimitConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    rateLimit: new RateLimitConfig(limit: 100, period: 60),
);
```

`rateLimit` ограничивает суммарное число разрешений для участвующих операций клиента.
Атрибут операции добавляет независимый предел:

```php
use Brahmic\ApiSutra\Attributes\Behavior\RateLimit;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[Get('/reports')]
#[RateLimit(limit: 5, period: 60, behavior: RateLimitBehavior::Throw)]
final class ReportsRequest extends AbstractRequest {}
```

При общей квоте 100/min пять отчётов расходуют по пять единиц обоих счётчиков.
Шестой отчёт не расходует ни одной квоты. Если общий остаток равен двум, можно
выполнить только два отчёта, даже когда собственная квота ещё свободна.

## Параметры

| RateLimitConfig | По умолчанию | Значение |
| --- | --- | --- |
| limit | 100 | Положительное число разрешений |
| period | 60 | Положительная длительность окна в **секундах** |
| behavior | RateLimitBehavior::Wait | Ожидание либо локальный отказ Throw |
| store | null | Прежний PSR-16 путь только для одной квоты без явного backend |
| key | null | Явная группа квоты; автоматические ключи описаны ниже |

| ClientConfig | По умолчанию | Значение |
| --- | --- | --- |
| rateLimit | null | Общая квота |
| includeClientQuota | true | Участие операций, если атрибут не уточнил его |
| rateLimitBackend | null | Атомарный backend; без него используются локальные счётчики или совместимый одиночный PSR-16 путь |

Период должен быть представим в микросекундах штатного sleeper
(`period <= intdiv(PHP_INT_MAX, 1_000_000)`); переполнение конца окна также отклоняется.
Неположительные значения дают ConfigurationException. Нулевой limit не выключает механизм.

## Участие и overrides

Для AbstractRequest собственная квота выбирается по приоритету: runtime options →
настройка экземпляра → атрибут с полной парой → собственная квота отсутствует.
Общая квота берётся отдельно из ClientConfig.rateLimit и не заменяется этим выбором.

- `#[RateLimit(limit: 5, period: 60)]` добавляет собственную квоту.
- `includeClientQuota: null` или отсутствие аргумента наследует клиентский флаг.
- `#[RateLimit(limit: 5, period: 60, includeClientQuota: false)]` учитывает только собственную.
- `#[RateLimit(includeClientQuota: false)]` исключает общую; без собственного runtime
  override квот у такого запроса нет. Числа общей квоты не копируются в собственную.
- `#[RateLimit(includeClientQuota: true)]` включает участие при клиентском default=false.
  Если общей квоты нет, флаг её не создаёт.
- `withoutRateLimit()` полностью отключает квоты данного запуска; `withRateLimit()`
  снова включает ограничение и задаёт собственную квоту по действующему приоритету overrides.

Пара limit/period задаётся целиком либо отсутствует. Пустой атрибут не меняет настройки;
key или отличный от Wait behavior без пары отклоняются. Валидация значений атрибута
происходит при применении в выполнении, до backend/HTTP; чтение metadata/inventory
не применяет квоты. Для RequestInterface вне AbstractRequest сохранён прежний путь
без introspection атрибутов и runtime overrides: только клиентское правило участия/квоты.

## Backend и ключи

Без внешнего backend состояние принадлежит экземпляру клиента и переживает переключение
fake/record/playback. Новый клиент создаёт независимые локальные счётчики. Локальные
окна используют monotonicMs и не сдвигаются при изменениях календарных часов.

В атомарном пути общая квота имеет отдельную группу client; собственная — группу
operation и класс запроса по умолчанию. `key` объединяет операции явно. Одинаковый текст
key общей и собственной квот не объединяет их друг с другом. Идентификаторы хешируются;
query/body/credentials не добавляются в них. В действующем окне одна группа должна
иметь одинаковые limit/period: конфликт даёт configuration_error без сброса счётчика.
После окончания окна допустимо новое определение.

Для нескольких workers используйте готовый
[Redis/phpredis backend](../integrations/redis.md). Он принимает весь набор одним
атомарным действием. Redis подключается явно, SDK не выбирает его по наличию Laravel.

PSR-16 store сохранён для **одной применимой квоты при rateLimitBackend=null**. Для
собственной квоты используются её store либо store клиента. Ключ этого пути прежний:
хеш `apisutra.rate-limit.v2:` + custom key/baseUrl. Прямой RateLimiter::acquire тоже
сохраняет прежний API с передаваемым ключом и Unix-секундами. get/set не гарантируют
межпроцессной атомарности, даже если сам cache store использует Redis.

Две квоты со store либо явный backend вместе со store несовместимы. Ошибка возникает
до чтения/записи и HTTP; конфликт клиентского store/backend — уже при создании конфига.
SDK не игнорирует store и не списывает половину набора в отдельное хранилище.
Настройки HTTP/auth cache от этого не меняются.

## Ожидание и ошибки

Одна фактическая HTTP-попытка получает по одному разрешению каждой квоты. Retry,
auth recovery, дочерние запросы и страницы также учитываются; cache hit и composite
обёртка без HTTP квоту не расходуют. Алгоритм — fixed window с первым разрешением
в начале окна; это не sliding window/token bucket и не гарантия отсутствия всплеска
на границе двух окон.

RateLimiter организует ожидание для всех backend. При нескольких блокирующих Wait
ждёт максимум их сроков и проверяет весь набор заново. Неблокирующая Throw-квота не
мешает ожиданию. Если хотя бы одна **блокирующая** квота имеет Throw — немедленный
RateLimitException; retryAfter равен максимуму всех блокирующих окон, округлённому
вверх в секунды. Это оценка, а не резервирование будущего места.

Ожидание и I/O входят в общий `RetryConfig.totalTimeoutMs`, если он задан. Это отдельная
настройка от HTTP timeout. После истечения бюджета HTTP не начинается. Доступные локальные
проверки transport/timeouts/body/destination выполняются до списания разрешения.

| Ситуация | Результат |
| --- | --- |
| Исчерпание с Throw | rate_limited, reason=local_rate_limit_exceeded, retryAfter в секундах |
| Ошибка backend | execution_error, reason=rate_limit_backend_error, stage=rate_limit_store; без автоматического retry |
| Deadline | timeout с соответствующим этапом |
| Некорректная конфигурация/конфликт определения | configuration_error до HTTP |

Действуют стандартные result-first, throwOnErrors и Promise-контракты. Локальный отказ
не создаёт HTTP 429: RateLimitException.response=null. Если предыдущая попытка получила
ответ, исключение сохраняет lastResponse и итоговый результат может содержать именно его.
Сырой previous доступен явно; стандартная диагностика не раскрывает текст ошибки backend.

Разрешение не равно доставленному HTTP. После grant может истечь deadline, пропасть
соединение или остановиться worker. Автоматического refund нет: SDK не знает,
дошёл ли запрос до внешнего API. Потеря состояния backend может обнулить квоты.

## Собственный атомарный backend

Реализуйте RateLimitBackendInterface из Brahmic\ApiSutra\RateLimiting:
`tryAcquire(array $quotas, ?int $timeoutMs = null): RateLimitDecision`.
RateLimitQuota содержит key, limit и **periodMs**. Backend возвращает granted либо
blockedIds и положительный **retryAfterMs**, проверяя все квоты до записи. Совпадающие
ID нормализуются в одно списание; противоречивые определения отклоняются. Backend не
спит и не выполняет HTTP. timeoutMs — оставшаяся длительность обращения, не абсолютное
время. Общий Wait/Throw и повторную проверку после ожидания обеспечивает RateLimiter.

## Миграция с прежнего замещения

До этого изменения атрибут/override заменял rateLimit клиента. Теперь он дополняет
общий лимит. Без custom key прежние правила могли использовать один счётчик с разными
параметрами; новая модель разделяет счётчики общей квоты и операций.

1. Если клиентское число должно быть общим пределом, сохраните rateLimit. Отчёт 5/min
   теперь дополнительно расходует общий 100/min. Исключения отметьте includeClientQuota=false.
2. Если общей квоты не должно быть, удалите клиентское определение; собственные правила
   объявите в SDK провайдера. Клиентского заменяемого default больше нет.
3. Для совместных квот удалите несовместимый store. Используйте локальный backend для
   одного экземпляра или Redis backend с согласованным scope для workers.
4. При переходе с общим хранилищем остановите старые workers, завершите начатые попытки
   и дождитесь конца старых окон, затем включите новый namespace. Автоматического переноса
   счётчиков и единой квоты при смешанных старых/новых workers нет.
5. Прямым metadata/reflection consumers учесть nullable limit/period атрибута.

Проверки неподдерживаемых timeout теперь идут до acquire также на PSR-16 пути:
при одновременной ошибке транспорта и исчерпании квоты первой видна локальная ошибка
конфигурации, без расхода квоты. Полная обратная совместимость не заявляется.

## Rate Limit (уровень клиента)
```php
use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

$config = $config->with(rateLimit: new RateLimitConfig(
    limit: 60,
    period: 60,
    behavior: RateLimitBehavior::Wait,
));
```

## Rate Limit для конкретного запроса
```php
use Brahmic\ApiSutra\Attributes\Behavior\RateLimit;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;

#[RateLimit(limit: 10, period: 1, behavior: RateLimitBehavior::Throw)]
final class Search extends AbstractRequest {}
```

`ClientConfig::rateLimit` задаёт общую квоту; атрибут и `withRateLimit()` задают
дополнительную квоту операции. Перед каждой HTTP-попыткой разрешение нужно по обеим.
`includeClientQuota: false` исключает общую квоту, `withoutRateLimit()` отключает все.
Без настройки backend учёт локальный, в экземпляре клиента. Общая квота имеет одну группу client, собственная — по классу запроса; custom key объединяет
операции. Пространства общей и собственной квот различаются.

PSR-16 store сохранён только для одной применимой квоты и не гарантирует атомарности
между процессами. Для нескольких workers есть необязательный
[Redis backend](../integrations/redis.md). Defaults, ожидание, ключи и изменения совместимости:
[полный контракт rate-limit](rate-limit.md).
