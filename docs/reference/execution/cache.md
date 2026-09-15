# Кеш HTTP-ответов

ApiSutra кеширует успешные ответы как application cache с TTL. Хранилище — PSR-16;
правила HTTP revalidation, `Vary` и `Cache-Control` автоматически не применяются.
Разрешайте кеширование только там, где допустимо повторно использовать ответ.

## Настройка без prefix

Для обычного клиента достаточно передать PSR-16 store. Пространство кеша SDK
определяет автоматически; `prefix` передавать не нужно.

```php
use Brahmic\ApiSutra\Auth\BearerAuthenticator;
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;

// $store — настроенное PSR-16 хранилище; $token — credentials подключения.
$config = new ClientConfig(
    baseUrl: 'https://api.example',
    auth: new BearerAuthenticator($token),
    cache: new CacheConfig(store: $store, ttl: 60),
);
```

Короткая запись `cache: $store` тоже включает кеширование разрешённых операций.
Можно разделить настройки: `cache: $store, cacheConfig: new CacheConfig(ttl: 60)`.
Дефолты `CacheConfig`: `ttl=3600`, `prefix=''`, `mode=Enabled`, `identity=null`.

Пространство включает класс SDK-клиента, `baseUrl`, фактически выбранную auth identity
и объявленный SDK контекст tenant. Одинаковые подключения разделяют кеш между
экземплярами клиента и процессами; разные credentials разделяются автоматически.
Имена auth scopes (`default`, `secondary`) сами по себе не являются identity.
Учитываются `AuthScope`, runtime auth options, `NoAuth` и auth policy. Анонимные
запросы имеют отдельное пространство и также работают без prefix.

Встроенные Bearer, Basic, API key, HMAC и Token authenticators предоставляют identity
автоматически. `AuthorizationSchemeAuthenticator` поддерживает token и статические
скалярные params со стандартным formatter. При динамическом params provider,
пользовательском formatter или `Stringable` params кеш пропускается: такие параметры
не дают достоверной identity без исполнения пользовательского кода.

Изменение credentials создаёт другое пространство. Фактическая смена Bearer-токена,
включая refresh, создаёт другой вариант ключа; сохранение cache hit между токенами
не гарантируется. HTTP-кеш не заменяет и не меняет хранилище auth-токенов.

### Дополнительное разделение

`prefix` — необязательная несекретная метка для дополнительного разделения уже
изолированного кеша. Например, `prefix: 'preview'`. Одинаковый prefix у разных
identity не объединяет их данные. Токены и пароли в него не передавайте.

Для одного исполнения можно заменить эту дополнительную метку:

```php
$execution = $request->withCacheScope('preview');
$result = $execution->send();
$execution->clearCache();
```

`withCacheScope()` возвращает новую execution-копию; исходный request не меняется.
Пустой runtime scope вызывает ошибку конфигурации. Override заменяет только prefix,
а автоматические границы provider/auth/tenant сохраняются.

### Контекст tenant и собственная авторизация: для разработчика SDK

Ядро не может угадать, что произвольное поле URL, body или header означает tenant.
Если один credential обслуживает несколько организаций, SDK провайдера описывает
этот контекст через `CacheIdentityProviderInterface`. Пользователь готового SDK
продолжает передавать обычные credentials и tenant, без ручных cache prefixes.

Контракт содержит `getCacheIdentity(?PreparedRequest $request = null): ?string`. Метод не выполняет HTTP, refresh,
запись в store или иные побочные эффекты. Он возвращает стабильный несекретный
идентификатор либо непрозрачный отпечаток. Разным tenant и правам доступа должны
соответствовать разные значения. `null` или пустая строка означают, что identity
не определена и кеширование нужно пропустить. Без `$request` возвращается стабильный
контекст подключения/группы для очистки. При переданном `$request` метод учитывает
фактические tenant/credentials после auth и hooks для custom key; обычные поля
операции включать не нужно. `CacheCredentialIdentity::forRequest()` помогает
учесть выбранные headers без учёта регистра и повторяющийся query-параметр.

Контракт можно применить в трёх местах:

- Собственный authenticator реализует его вместе с `AuthenticatorInterface`.
  Без контракта HTTP-кеш пропускается даже при явном prefix.
- `CacheConfig::identity` принимает объект контекста подключения, например DTO
  с tenant ID. Он дополняет auth identity и разделяет также очистку клиента.
- Request реализует контракт, если tenant выбирается для конкретного запроса.
  Его identity разделяет группы ответов внутри пространства подключения.

Пример запроса SDK провайдера, где организация передаётся заголовком:

```php
use Brahmic\ApiSutra\Attributes\Behavior\Cache;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Auth\CacheCredentialIdentity;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

#[Get('/summary')]
#[Cache(key: 'summary')]
final class TenantSummaryRequest extends AbstractRequest implements CacheIdentityProviderInterface
{
    public function __construct(
        #[Header('X-Tenant-Id')] public readonly string $tenantId,
    ) {}

    public function getCacheIdentity(?PreparedRequest $request = null): ?string
    {
        return CacheCredentialIdentity::forRequest($this->tenantId, $request, ['X-Tenant-Id']);
    }
}
```

Для контекста подключения SDK аналогично передаёт объект контракта в
`new CacheConfig(store: $store, identity: $tenantContext)`. Конфигурационная identity
не заменяет обязательную identity собственного authenticator. Если выбранный
контракт возвращает неопределённое значение, запрос выполняется без кеширования;
при DEBUG-логировании доступна причина `cache_reason=unknown_identity`.

Credentials, добавляемые транспортом (например, mTLS или cookie jar), и tenant
из внешнего изменяемого состояния должны быть представлены SDK в этом контракте.
Если контекст пока неизвестен, возвращайте `null` или отключайте кеширование.

## Разрешение кеширования и режимы

Глобальная конфигурация допускает кеширование GET. Для POST/PUT/PATCH/DELETE
нужен явный opt-in на операции: `#[Cache]`, `withCache()`, `withCacheReadOnly()`
или `withCacheWriteOnly()`. `withCacheScope()` задаёт только пространство и сам
по себе не разрешает кеширование POST.

Приоритет режима: runtime → атрибут → конфигурация.

| Режим | Чтение ответа | Запись свежего ответа |
| --- | --- | --- |
| Enabled | Да | Да |
| ReadOnly | Да | Нет |
| WriteOnly | Нет | Да |
| Disabled | Нет | Нет |

ReadOnly не создаёт даже служебные поколения кеша. WriteOnly читает служебные
поколения для корректной очистки, но не читает сохранённый HTTP-ответ.
`withoutCache()` выключает чтение и запись; TTL можно задавать через `withCache(60)`.
Вызов `withCache()` без нового TTL сохраняет ранее установленный runtime TTL.

Ошибочные ответы не записываются. Попадание в кеш не продлевает TTL ответа.
Файловые upload/download автоматически пропускают глобальный кеш. Явный активный
`withCache()`/`#[Cache]` на файловой операции даёт `configuration_error` до auth/HTTP,
даже если cache store не задан; runtime `withoutCache()` снимает конфликт.
Это относится и к `FileInput` в Base64. Подробнее — [файлы](../../guides/recipes/files.md).

## Идентичность ответа

Автоматический ключ учитывает HTTP-метод, фактический URI после auth/hooks,
body и все headers. Порядок query и списков, повторяющиеся параметры сохраняются;
имена headers сравниваются без учёта регистра. Изменение токена, языка, tenant header
или подписи создаёт другой вариант ответа. Учёт всех headers может снизить число
cache hits при изменчивых служебных заголовках.

`#[Cache(key: 'name')]` задаёт явный логический ключ **внутри автоматического
пространства identity/tenant**. Он намеренно объединяет обычные варианты URI, тела
и headers. Разные origin, авторизация и объявленные tenant не объединяются.
Дополнительный защитный отпечаток учитывает userinfo и известные credential headers
и query-параметры фактически подготовленного запроса. Это не универсальный детектор
tenant: специфичные поля провайдер объявляет через контракт выше.

Встроенные authenticators дополнительно учитывают свои фактические credential-поля
после hooks, включая нестандартные header/query API key. Изменение таких данных
разделяет custom-кеш; обычные изменения URI, body и headers его не разделяют.
Собственный authenticator и tenant-контракт должны аналогично учитывать итоговый
`PreparedRequest`. Если итоговый контекст нельзя достоверно определить, метод
возвращает `null`, и кеш пропускается (`cache_reason=unknown_identity`).

Провайдер отвечает за эквивалентность объединяемых запросов. Поле `key` не включает
кеширование вопреки итоговому режиму Disabled.

Ключи, передаваемые в store, — версионированные хеши фиксированной длины;
сырые URI, credentials, prefix и пользовательский key в них не записываются.
Если запрос изменился при auth retry, ответ не записывается под прежним ключом;
доступна причина `cache_reason=request_changed`.

## Очистка

- `$request->clearCache()` и `$execution->clearCache()` инвалидируют варианты
  исходного запроса в выбранном пространстве. Учитываются начальные HTTP-данные
  и pagination options; traceId, TTL и режим доступа не создают отдельную группу.
- Для custom key очищается его общая группа внутри пространства.
- `$client->clearCache()` инвалидирует автоматические пространства default auth,
  настроенных auth scopes и анонимных запросов этого подключения, включая tenant-группы
  requests. Учитываются класс SDK-клиента, baseUrl, конфигурационный tenant и prefix;
  очистка работает и без prefix. Неизвестные auth identities пропускаются.
- Клиенты с одинаковыми подключениями разделяют также очистку. Чтобы разделять
  очистку tenant на уровне клиента, SDK задаёт `CacheConfig::identity`.
  Runtime-пространства очищайте через соответствующий execution.

Очистка не вызывает auth refresh или BeforeSend hooks. Она меняет непрозрачное
поколение группы/пространства. Уже начатый запрос не может записать ответ в новое
поколение; физически старые ответы остаются в backend до своего TTL.
Полная очистка backend выполняется владельцем store явно, вне API клиента.

Служебное поколение пространства хранится без TTL, поколения групп — с TTL
`max(60, TTL запроса)` секунд. Истечение или вытеснение поколения может привести
к дополнительному cache miss, но не возвращает ранее инвалидированные ответы.
PSR-16 не гарантирует атомарную инициализацию: при гонке допустим лишний miss.
ReadOnly не восстанавливает отсутствующие поколения. Неудача записи поколения
или ответа возвращается через штатный механизм ошибок; очистка при неудаче бросает
ошибку конфигурации.

## Переход с предыдущего поведения

Ранее общий store позволял кешировать запросы без identity scope и автоматически
кешировал обычный POST. Теперь пространство определяется автоматически, а для
допустимых операций изменения нужен отдельный opt-in. Собственным authenticators
и SDK с отдельным tenant нужно объявить identity. Формат ключей изменён;
старые записи истекают по прежнему TTL.

Требование обязательного prefix из первой поставки AS-04 отменено: его можно убрать.
Если оставить prefix, он станет дополнительной меткой внутри автоматической identity,
поэтому одинаковый prefix больше не объединяет разные подключения.

Явный `#[Cache(key: ...)]` сохраняет объединение запросов, но физический ключ в store
теперь хешируется и включает пространство. Чтение не продлевает TTL. Очистка клиента
больше не удаляет посторонние записи общего backend.

Описание атрибута: [Behavior attributes](../attributes/behavior.md).

## Auth-токены и необязательная служба блокировок

Переданный store также используется для токенов, отдельно от HTTP response cache.
Для встроенного TokenAuthenticator область определяется автоматически; prefix не нужен.
HTTP-настройки `withoutCache()`/`CacheMode::Disabled` не выключают хранение токена.

`CacheConfig::locks` — необязательный AuthLockProviderInterface для координации refresh
между процессами. Если он не передан, SDK использует capability выбранного store
либо локальную службу. Все прежние аргументы CacheConfig сохраняются.
Контракт, ограничения, обработка ошибок и миграция — в
[руководстве auth](../auth/tokens.md#refresh-lock).
