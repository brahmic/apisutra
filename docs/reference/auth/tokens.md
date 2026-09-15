# Токены, refresh и кеш

## Refresh‑логика
Параметры:
- `authRetryOn401` — повтор при 401
- `authRetryAttempts` — количество попыток

## Изоляция состояния и ожидание refresh

Встроенный TokenAuthenticator автоматически разделяет токены между конфигурациями
клиента, scope и объявленным контекстом подключения. Дополнительные prefix/account ID
не нужны. Без store работает локальное хранение, а локальная блокировка выбирается
без дополнительных параметров. Межпроцессная гарантия требует поддерживаемого backend.

При исчерпании ожидания refresh SDK возвращает `timeout` без основного HTTP;
существующий общий deadline сохраняет приоритет. Полный контракт и последствия
обновления — [изоляция токенов](tokens.md#изоляция-токенов-без-дополнительных-настроек).

## TokenAuthenticator + refresh
```php
use Brahmic\ApiSutra\Auth\TokenAuthenticator;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;

#[Post('/auth/token')]
#[Returns(AuthTokenResponseDto::class)]
final class AuthTokenRequest extends AbstractRequest
{
    public function __construct(
        #[Body]
        public string $username,
        #[Body]
        public string $password,
    ) {}
}

final readonly class AuthTokenResponseDto extends AbstractResponseDto
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
    ) {}
}

$config = $config->with(
    auth: new TokenAuthenticator(
        username: 'user',
        password: 'pass',
        tokenTtl: 600,
        refreshRequestClass: AuthTokenRequest::class,
    ),
);
```

Для общего кеша токена передайте PSR‑16 store в `ClientConfig::cacheStore`.
Это тот же backend, который использует HTTP-кеш; `CacheConfig` содержит только параметры.
`with(cacheStore: null)` отключает общий store в копии без очистки записей.
Локальное хранение и явно заданный locks сохраняются. [Полная семантика](../execution/cache.md#копирование-и-отключение).

## Refresh и 401
- `AuthenticatorInterface::shouldRefresh()` и `getRefreshRequest()` управляют обновлением токена.
- `authRetryOn401` управляет автоматическим восстановлением после 401 (по умолчанию включено); явный обычный `retryOn: [401]` независим.
- `authRetryAttempts` задаёт число попыток refresh.

### Изоляция токенов без дополнительных настроек

Обычный `ClientConfig(baseUrl: ..., auth: new TokenAuthenticator(...))` не требует
новых параметров. Без cache store токен сохраняется локально внутри клиента.
С PSR-16 store SDK автоматически разделяет токены по классу клиента, настроенному
base URL (включая base path), identity authenticator, выбранному auth scope и
объявленной `CacheConfig::identity`, если она есть. Identity встроенного
TokenAuthenticator учитывает username, password и refreshRequestClass.

Никаких обязательных prefix, account ID или lock key нет. SDK не вводит сущности
аккаунтов внешнего API: он разделяет уже переданные конфигурации авторизации.
Адрес конкретного ресурса и текущее значение access token не меняют область refresh.
Origin policy продолжает отдельно определять, куда разрешено передать credentials.

Физические token/lock keys — отдельные версионированные digest длиной 64 символа,
без открытых username, credentials или URL. Одинаковый объявленный контекст получает
одинаковые ключи в разных процессах. Digest не является шифрованием секретов.

Встроенный TokenAuthenticator получает отдельное состояние token/expiresAt для
каждой привязки к клиенту и scope. Повторное использование исходного auth-объекта
в другом клиенте не переносит его токен. Прямой `setCache()` при смене store очищает
старую локальную привязку; cache miss в прежнем store сохраняет действующий локальный
токен. Методы `freshForContext()`, `loadFromCache()` и `tokenVersion()` используются
SDK для привязки и перечитывания; вызывать их в обычном клиентском коде не требуется.

### Refresh lock

По умолчанию SDK использует локальную службу блокировок без настройки backend.
Она действует внутри данного клиента/pipeline, не между независимыми клиентами
или процессами. PSR-16 cache остаётся пригодным для хранения токенов, но сам по себе
не гарантирует атомарных блокировок. Одного дополнительного метода `add()` недостаточно.

Выбор службы происходит автоматически в таком порядке:

1. Необязательный `CacheConfig::locks`, если явно задан.
2. Выбранный cache store, если он реализует `AuthLockProviderInterface`.
3. Локальная служба SDK.

Для собственных backend контракт находится в
`Brahmic\ApiSutra\Contracts\Interfaces\Auth`: `AuthLockProviderInterface::acquire()`
возвращает `AuthLockLeaseInterface` либо null, если блокировка занята. Provider обязан
атомарно захватывать отсутствующую/истёкшую запись. `lease->release()` атомарно удаляет
только запись своего владельца; при потере владения возвращает false. Последовательность
`get → compare → delete` без атомарности этому контракту не соответствует.

Отдельный backend подключают, когда нужна координация между процессами:

```php
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;

// $lockProvider — реализация AuthLockProviderInterface выбранного хранилища.
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    cacheStore: $tokenStore,
    cacheConfig: new CacheConfig(locks: $lockProvider),
);
```

Это необязательное расширение. SDK не подключает Redis или Laravel автоматически
и не обещает адаптер для каждого Laravel lock driver. Ошибка явно выбранного backend
не включает тихий fallback к локальной блокировке.

TTL и максимальное ожидание берутся из `ClientConfig::timeout` с границами 5–30 секунд.
Локальный срок измеряется монотонными часами, ожидание использует SleeperInterface
и общий deadline. Блокировка защищает только в пределах своего TTL: продление lease,
fencing и гарантия единственного refresh после истечения TTL не реализованы.

Встроенный TokenAuthenticator перечитывает store при ожидании и после захвата lease.
Действующий токен другого владельца позволяет пропустить лишний refresh. После 401
прежний отвергнутый токен не считается обновлением: требуется другое значение.
Если lock не получен и пригодный токен не появился, результат — `timeout` с причиной
`auth_refresh_lock_timeout`, stage `auth_lock_wait`; основной HTTP не отправляется.
Истечение общего бюджета сохраняет `execution_deadline_exceeded` и исходный 401,
если он уже был. Общий HTTP retry не повторяет ошибки auth-блокировки.

Исключение backend даёт `execution_error` с причиной `auth_lock_backend_error`.
Если release упал после успешного refresh, основной HTTP не отправляется. При
существующем отказе refresh/deadline ошибка release не подменяет первичную причину;
в logger передаётся только безопасное описание вторичной ошибки.

## Кеш для токенов

CacheAwareInterface получает изолированный PSR-16 view, а не исходный общий store.
`getCacheKey()` задаёт логический ключ; view преобразует его в физическую область,
включая bulk-операции. `clear()` отклоняется, чтобы не очистить чужие данные;
используйте `delete()`/`deleteMultiple()` для своих логических ключей.

Для общего token store пользовательский authenticator объявляет стабильную identity
через CacheIdentityProviderInterface. При отсутствии identity либо неопределённой
identity подключения SDK даёт локальное хранилище этой auth внутри клиента. При
DEBUG-логировании доступна причина `auth_cache_identity_unavailable`. Настраивать
фиктивный prefix вместо identity не нужно.

Произвольное mutable состояние собственного authenticator остаётся ответственностью
его автора. SDK не клонирует такие объекты неявно и не предполагает, что любой
`setCache()` перечитывает токен. Автоматическое разделение памяти и оптимизация
повторного refresh после ожидания обеспечены для встроенного TokenAuthenticator.

### Миграция token cache

Старые `auth_token_<username>` и `auth_refresh_lock:...` не читаются как fallback и
не удаляются глобально. После обновления возможен однократный refresh: безопасно
определить владельца старой записи только по username нельзя. Предзаполненные
старые token records и токен, вручную помещённый в исходный unbound TokenAuthenticator,
не переносятся в новые pipeline-привязки.

Если custom auth ранее получала исходный cache store, учтите scoped view и локальный
режим без identity. Если backend выбирался благодаря одному `add()`, для общей
блокировки потребуется реализация атомарного контракта. Настройка обычного клиента
с встроенным auth остаётся прежней. Прямое применение TokenAuthenticator без клиента
разделяет credentials, но не может автоматически учитывать неизвестный ему base URL.

## Бюджет авторизации и refresh

Если задан `RetryConfig::totalTimeoutMs`, начальная авторизация, ожидание refresh lock
и refresh после 401 используют deadline родителя. Дочерний запрос не получает новый
полный бюджет. При его исчерпании сохраняется `ExecutionDeadlineException`, повтор
refresh не начинается. Подробности — [Timeouts & Delay](../execution/deadlines.md).

## Восстановление после 401 и миграция

Автоматический auth retry требует доступного и успешного обновления авторизации.
Без auth, с фиксированным Bearer/API key или без refresh-запроса SDK возвращает
первый настоящий 401. Дополнительная настройка не нужна. Успешный refresh должен
вернуть `ResponseDtoInterface` и пройти `processTokenResponse()`; пустой ответ
не разрешает повтор. Возврат того же значения токена допустим.

При отказе refresh после 401 результат содержит исходный 401 со всеми headers/body,
код `unauthorized` и `reason=auth_refresh_failed`. Исключение
`AuthRefreshFailedException` наследует `UnauthorizedException`: существующий catch
продолжает работать. Его `dependencyResult` содержит фактический результат refresh,
например HTTP 503 или ошибку декодирования; `recoveryException` — причину восстановления.
Эти объекты доступны для явного разбора и не включаются в автоматический safe context.
При сбое до отправки refresh результат зависимости может отсутствовать.

При initial refresh до основного HTTP возвращается ошибка самой зависимости без
искусственного 401. Общий deadline сохраняет приоритет `timeout`; ошибки lock
сохраняют свои причины. SDK не повторяет основную операцию после отказа восстановления.
`client->send(new Request())` и `sendAsync()` используют реально выполняющего клиента
для refresh без обязательного предварительного `setClient()`.

Миграция: custom authenticator, выполнявший неявную ротацию только внутри повторного
`authenticate()`, должен предоставлять `getRefreshRequest()` и обрабатывать типизированный
ответ. Нового обязательного метода интерфейса нет. Если нужен обычный повтор 401,
он задаётся существующей retry-политикой с учётом безопасности операции и тела.
