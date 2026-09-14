# Response attributes

Атрибуты, описывающие тип ответа и режим загрузки файлов.

## Когда использовать
- **Returns** — когда нужен DTO‑ответ или unwrap вложенных данных.
- **Download** — когда ответом является файл.
- **RawResponse** — когда нужна строка тела без декодирования.
- **ContinuationResult** — когда готовность финала и его тип отличаются от стартового ответа.

## Returns
**Target:** class  
**Параметры:**  
- `response: string` — класс DTO результата  
- `unwrap?: string` — путь к данным внутри ответа  
- `type?: string` — override DTO класса после unwrap  

`type` нужен, когда у разных запросов один и тот же `unwrap`,
но нужен другой DTO (например, разные модели в одном и том же контейнере).
Если `type` не задан — используется `response`.

Гидратор клиента применяет подключённый `ClientConfig::hydrationRules`, в том числе
к plain-классам. [Внешние правила и диагностика](../hydration-rules.md) одинаковы
для синхронного результата и promise.

Пример:
```php
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Returns(UserDto::class, unwrap: 'data.user')]
final class GetUser extends AbstractRequest {}
```

### Строгий unwrap

Указанный `unwrap` — обязательный путь в dot-нотации, включая индексы списков
(`data.0`). Путь проверяется после `BeforeHydrate`. Если его нет, результат содержит
`hydration_error` с причиной `unwrap_path_missing`; HTTP-ответ сохраняется.
Корень документа не подставляется вместо отсутствующих данных.

Найденный `null` отличается от отсутствующего пути. `Returns` объявляет обязательный
DTO: null или scalar вместо его данных дают `unexpected_response_shape`. Пустой
массив передаётся обычной гидратации и может быть допустимым для DTO с defaults.
`unwrap: null` означает отсутствие извлечения. Для самой проверки unwrap
дополнительные настройки клиента не нужны.

Если отсутствие объекта нормально, объявите DTO-обёртку с nullable-полем:

```php
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;

final readonly class OrderDto extends AbstractDto
{
    public function __construct(public string $id) {}
}

final readonly class ActiveOrderDto extends AbstractDto
{
    public function __construct(public ?OrderDto $order) {}
}

#[Returns(ActiveOrderDto::class, unwrap: 'data')]
final class GetActiveOrder extends AbstractRequest {}
```

Для `{"data":{"order":null}}` результат успешен и `order === null`.
Прямой nullable DTO результата этим атрибутом не объявляется. Корневой null/204
без DTO сохраняет [свой контракт](../client-config/responses-errors.md#успешный-ответ-без-dto).
Для DTO без unwrap прежняя нормализация корневого null/204/пустого тела в `[]` сохраняется.

При обновлении проверьте запросы, полагавшиеся на fallback к корню. Исправьте путь,
уберите unwrap для корневого DTO или явно нормализуйте варианты ответа через
`BeforeHydrate`/ResponseHandler. У `ContinuationResult::unwrap` и pagination itemsPath
отдельные правила; строгий контракт здесь относится к `Returns`.

## ContinuationResult

**Target:** class. `finalType: string` — обязательный класс финального DTO;
`unwrap?: string` — путь финала и критерий его присутствия;
`pollRequest?: string` — класс запроса с одним обязательным scalar token-параметром;
`defaultMode?: ContinuationMode` — режим провайдера;
`stateResolver?: string` — класс `ContinuationStateResolverInterface` без обязательных
аргументов конструктора.

Приоритет критерия: `stateResolver` атрибута → непустой `unwrap` → resolver клиента.
Если критерия нет, ожидание даёт configuration-ошибку до polling. В отличие от
`Returns`, отсутствие/null данных по `unwrap` здесь означает Pending. Ошибка данных Ready
немедленно завершает ожидание и сохраняет полный путь и последний HTTP-ответ.
Полный контракт, пример resolver и миграция — в [Provider Async Await](../provider-async-await.md).

## RawResponse

**Target:** class. Параметров нет. Опционален; настройки ClientConfig не нужны.

```php
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\RawResponse;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/export')]
#[RawResponse]
final class ExportRequest extends AbstractRequest
{
}

$result = $client->send(new ExportRequest())->raw();
$text = $result->data;
```

Для одного исполнения: `$request->withRawResponse()->send()`. Приоритет:
runtime → атрибут → стандартный Auto. `withRawResponse(false)` выбирает Auto,
`withRawResponse(null)` снимает override и возвращает наследование атрибута.
Цепочка не меняет исходный запрос.

Raw возвращает body, предоставленное транспортом, без JSON и response format handlers:
`'null'` остаётся строкой, пустое тело и 204 дают `''`. Режим полезен при ошибочном
Content-Type или намеренном чтении JSON как строки. В Auto неизвестный явно указанный
не-JSON формат без DTO и так возвращается строкой; подробности —
[контракт ответа](../client-config/responses-errors.md#успешный-ответ-без-dto).

Raw несовместим с Returns/response DTO, пагинацией и download: итоговое сочетание
отклоняется как `configuration_error` до HTTP. Для файлов используйте Download.
BeforeHydrate для строки пропускается; остальные hooks сохраняются. HTTP-ошибка
не становится успехом: например, 429 остаётся ошибкой независимо от Raw.

Для ручной диагностики исходное тело уже есть в `$result->response?->body`, даже
при ошибке декодирования в result-first режиме. `send()->raw()` получает весь
ExecutionResult, а не включает RawResponse. При `throwOnErrors` исключение может
прервать получение результата. HTTP cache хранит исходный response: Auto и Raw
используют один ключ и не требуют префиксов или другого кеша.

## Download
**Target:** class  
**Параметры:** нет  
**Эффект:** помечает запрос как загрузку файла; результат — FileResponse.

Тело принимается потоком во временный файл без дополнительных настроек.
`withDownloadTo()` задаёт локальный путь или writable поток для окончательного
результата. Владение, кеш и совместимость описаны в [гайде файлов](../files.md).
