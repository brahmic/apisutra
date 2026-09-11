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

## Download
**Target:** class  
**Параметры:** нет  
**Эффект:** помечает запрос как загрузку файла; результат — FileResponse.
