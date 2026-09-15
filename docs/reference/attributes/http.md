# HTTP-атрибуты

## Сигнатуры и targets

Имена классов относятся к `Brahmic\ApiSutra\Attributes\Http`.
Сигнатуры показывают параметры и defaults конструктора; target указывает допустимое
место атрибута. Поведение и приоритеты описаны в тематических ссылках ниже.

| Атрибут | Target | Конструктор |
| --- | --- | --- |
| Delete | CLASS | `Delete(string $path)` |
| Get | CLASS | `Get(string $path)` |
| Patch | CLASS | `Patch(string $path)` |
| Post | CLASS | `Post(string $path)` |
| Put | CLASS | `Put(string $path)` |

Атрибуты, задающие HTTP‑метод и endpoint запроса. Применяются к классу запроса.

## Когда использовать
- **Get** — безопасные чтения без сайд‑эффектов.
- **Post** — создание или сложные запросы с body.
- **Put/Patch** — обновление ресурса (полное/частичное).
- **Delete** — удаление или отмена.

## Get
**Target:** class
**Параметры:** `path: string` — путь запроса
**Эффект:** метод GET + endpoint

Пример:
```php
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;

#[Get('/users/{id}')]
final class GetUser extends AbstractRequest {}
```

## Post
**Target:** class
**Параметры:** `path: string`
**Эффект:** метод POST + endpoint

## Put
**Target:** class
**Параметры:** `path: string`
**Эффект:** метод PUT + endpoint

## Patch
**Target:** class
**Параметры:** `path: string`
**Эффект:** метод PATCH + endpoint

## Delete
**Target:** class
**Параметры:** `path: string`
**Эффект:** метод DELETE + endpoint

## Примечания
- Плейсхолдеры в `path` можно заполнять через `#[Path]`.
- Если имя свойства совпадает с `{id}`, `#[Path]` не требуется.
  Используйте `#[Path('id')]`, когда имя свойства другое
  или плейсхолдеров несколько.
