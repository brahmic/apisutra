# Response attributes

Атрибуты, описывающие тип ответа и режим загрузки файлов.

## Когда использовать
- **Returns** — когда нужен DTO‑ответ или unwrap вложенных данных.
- **Download** — когда ответом является файл.

## Returns
**Target:** class  
**Параметры:**  
- `response: string` — класс DTO результата  
- `unwrap?: string` — путь к данным внутри ответа  
- `type?: string` — override DTO класса после unwrap  

`type` нужен, когда у разных запросов один и тот же `unwrap`,
но нужен другой DTO (например, разные модели в одном и том же контейнере).
Если `type` не задан — используется `response`.

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
`unwrap: null` означает отсутствие извлечения. Новых настроек клиента нет.

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

## Download
**Target:** class  
**Параметры:** нет  
**Эффект:** помечает запрос как загрузку файла; результат — FileResponse.

Тело принимается потоком во временный файл без дополнительных настроек.
`withDownloadTo()` задаёт локальный путь или writable поток для окончательного
результата. Владение, кеш и совместимость описаны в [гайде файлов](../files.md).
