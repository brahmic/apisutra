# Внешние и подписанные URL

Передайте готовую ссылку через `$request->withUrl($url)`. SDK использует её целиком,
без base path/query клиента. Дополнительных настроек для обычной подписанной ссылки
не нужно. Абсолютный `getEndpoint()` имеет тот же контракт.

```php
declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Http\FileFormat;
use Brahmic\ApiSutra\VO\Files\FileInput;

#[Post('/upload-sessions')]
final class CreateUploadSessionRequest extends AbstractRequest {}

// Пример API, которое возвращает URL для POST multipart.
#[Post('/upload')]
final class UploadToSessionRequest extends AbstractRequest
{
    /** @param list<FileInput> $files */
    public function __construct(
        #[File(name: 'file', format: FileFormat::Multipart)]
        public array $files,
    ) {}
}

$session = (new CreateUploadSessionRequest())->setClient($client)->dataOrFail();
$upload = new UploadToSessionRequest([FileInput::fromPath('/tmp/report.pdf')]);
$result = $upload->setClient($client)->withUrl($session['url'])->send()->raw();
```

Метод, формат тела и нужные заголовки задаются по контракту провайдера. Если сессия
возвращает специальные заголовки, задайте их через `withHeader()`. Сохранение URL
не исправляет неверные method/body/signed headers и не означает проверку подписи SDK.
Binary/multipart upload и download поддерживают [потоковую передачу](files.md),
включая сохранение в путь или пользовательский поток.

## Правила готового адреса

- Приоритет: `withUrl()` → абсолютный endpoint → относительный endpoint с effective base URL.
  Новый `withUrl()` заменяет предыдущий; `withoutUrl()` очищает только этот override.
  Ранее заданный `withBaseUrl()` снова действует после очистки. Исходный запрос не меняется.
- Поддерживаются HTTP/HTTPS, ASCII/punycode host и IPv6. URL должен быть уже закодирован.
  Userinfo, другие схемы, `//host`, пробелы, управляющие символы и неверные `%`-последовательности
  вызывают `configuration_error`. Unicode в path/query передаётся percent-encoded.
- Path/query сохраняются: `%2f`, `%2F`, `+`, `%20`, повторяющиеся ключи, порядок,
  пустой `?` и граничные `&`. Fragment удаляется; пустой path становится `/`.
  Штатный cURL-адаптер также сохраняет dot-segments без разрешения `..`.
- Base URL, placeholders относительного endpoint и дополнительные query не применяются.
  Если поля, pagination, continuation или явно включённый enricher создают query-пару,
  SDK возвращает `configuration_error`, а не игнорирует её. Пропущенный null/пустой список
  пары не создаёт; включённый null и пустая строка создают пару и конфликтуют.
- Query auth не может дописывать ключ в готовый URL, даже при явном включении.
  Динамические query для обычного API собирайте через относительный endpoint.

## Авторизация и zero-config

Для полного URL автоматически не применяются auth/auth scopes клиента,
`credentialsConfig` и общие `requestEnrichers`, даже на том же origin.
Явные поля запроса, файл и runtime headers считаются данными для данного назначения.

При относительном endpoint текущая авторизация сохраняется на origin исходного
`ClientConfig.baseUrl`. Если `withBaseUrl()` меняет origin, автоматическое наследование
auth/credentials/enrichers выключается. Origin — схема, host и effective port;
регистр host/default port не меняет origin, поддомен и другая схема меняют.

Исключения задаются через необязательную [OriginPolicy](client-config/auth.md#originpolicy)
и явный выбор auth: `withAuth()`, `withAuthScope()`, `forceAuth()` или `forceAuthScope()`.
`forceAuth()` не отменяет origin policy. Разрешённый origin сам по себе не включает auth.
На том же origin отдельное разрешение не нужно, но полный URL требует явного выбора auth.

Для явного enrichment используйте `withCredentialsEnrichment(true)` и отдельно
`withRequestEnrichers(true)`. На чужом origin они также требуют разрешения.
`withRequestEnrichers(false)` отключает общие enrichers для любого исполнения.
Это не отменяет запрет изменения готового query. При выключенном auth ответ 401
не вызывает refresh исходного аккаунта. У разрешённого auth сохраняются scope и deadline.

## Кеш, redirects и диагностика

Полный URL автоматически обходит HTTP cache. Явное включение кеша, включая атрибут
запроса, вызывает `configuration_error`: SDK не знает срока действия подписи.
Существующая семантика custom key обычных запросов сохранена.

Штатный адаптер не следует redirects. Ответ 3xx возвращается в обычную обработку
HTTP-результата; разрешение origin не разрешает переход по `Location`.
Поздняя смена origin через stages/hooks/auth/retry handler, а для готового URL —
любое изменение адреса, отклоняется до cache lookup/HTTP.

В безопасных `requestDebug()`, логах и записанных fixtures готовая ссылка заменяется
на origin с `/[redacted]`. Маскируются также отражённые полная ссылка и request target.
Для этого не нужен `RedactionPolicy`. Дополнительные правила по-прежнему нужны для
других секретных полей API. Исходные `response`, `exception`, данные результата и
`requestDebug(false)` — raw-доступ, их нельзя считать безопасным экспортом.

## Свой транспорт и миграция

Штатный `HttpTransport::createDefault()` обеспечивает контракт автоматически.
Для своего транспорта/PSR-адаптера см. [DestinationAwareInterface](transport.md#изоляция-назначения).
Отсутствие поддержки даёт `configuration_error` до auth/refresh и отправки защищаемого запроса.
Синхронный и promise-интерфейсы используют те же правила.

Изменение не полностью обратно совместимо: прежний внешний `withBaseUrl()` мог
автоматически передавать токен, enrichment и HTTP-client defaults. Теперь нужны
явные данные назначения либо auth + разрешённый origin. Для самостоятельного сервиса
можно создать отдельный клиент с его base URL и credentials.
Готовые ссылки больше не требуют ручного обхода base URL; обычные относительные
запросы к исходному API не требуют новых настроек.
