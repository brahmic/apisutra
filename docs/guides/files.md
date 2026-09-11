# Файлы

Гайд по загрузке и скачиванию файлов.

## Загрузка файлов
```php
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Http\FileFormat;
use Brahmic\ApiSutra\VO\Files\FileInput;

#[Post('/files')]
final class UploadFile extends AbstractRequest
{
    public function __construct(
        #[File('file', format: FileFormat::Multipart)]
        public FileInput $file,
    ) {}
}

$request = new UploadFile(FileInput::fromPath('/tmp/report.pdf'));
$request->send();
```

Поддерживаемые форматы: `Multipart`, `Binary`, `Base64`.

### Повторная отправка

При разрешённом retry ядро автоматически восстанавливает seekable-поток до исходной
позиции, которая может быть ненулевой. Для multipart сохраняются позиции файловых
частей, boundary и байты тела. Никаких настроек перемотки не требуется; SDK не закрывает
переданный пользователем поток. Пустой файл остаётся допустимым.

Non-seekable поток допускает первую отправку, но не повторную. При невозможном tell/seek
или замене тела после отправки SDK прекращает повтор, сохраняя исходную ошибку и
`retryRefusalReason` в её контексте. Неявная буферизация и фабрики повторяемых потоков
не применяются. Гарантия повторяемости относится к попыткам одного выполнения;
не меняйте содержимое файла извне и не используйте один поток одновременно в разных вызовах.

POST-загрузка требует подтверждения безопасности операции через Retry.safe или
RetryConfig.safeMethods; одного включения retry недостаточно. См.
[контракт повторов](retries-rate-limit.md).
Binary upload и download пока материализуют содержимое в памяти; исправление retry
не превращает их в потоковую обработку больших файлов.

### Форматы загрузки
- **Multipart** — стандартный `multipart/form-data`.
- **Binary** — один файл отправляется как raw body; MIME берётся из `FileInput`.
- **Base64** — файл кодируется в base64 и кладётся в JSON‑body по имени поля.

Пример Binary:
```php
#[File('file', format: FileFormat::Binary)]
public FileInput $file;
```

Пример Base64:
```php
#[File('file', format: FileFormat::Base64)]
public FileInput $file;
```

### Несколько файлов
```php
#[File('files')]
public array $files;
```
Если в массиве несколько `FileInput`, они будут отправлены как набор файлов.

### FileInput фабрики
- `fromPath()` — из файла (бросает `ConfigurationException` при ошибке)
- `tryFromPath()` — из файла, возвращает `?FileInput` при ошибке (для path-flow без исключений)
- `fromContent()` — из строки
- `fromStream()` — из PSR‑7 stream
- `withMimeType()` — переопределить MIME‑тип

Для пути, приходящего от пользователя/формы, используйте `tryFromPath()` и при `null` добавляйте `ValidationError` в `validateCustom()`.

## Скачивание файлов
```php
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Download;

#[Get('/files/{id}')]
#[Download]
final class DownloadFile extends AbstractRequest
{
    public function __construct(public int $id) {}
}

$file = (new DownloadFile(10))->send()->dataOrFail();
$file->saveTo('/tmp/file.pdf');
```

`FileResponse` поддерживает:
- `content()` / `stream()`
- `filename()` / `mimeType()` / `size()`
- `isArchive()` / `asArchive()`

## Base64 в ответе
Если поле DTO типизировано как `Base64File`, SDK автоматически декодирует строку:
```php
use Brahmic\ApiSutra\VO\Files\Base64File;

public Base64File $document;
```

Важно:
- встроенный `Base64File` ожидает **чистую base64-строку**
- если провайдер возвращает data-uri вида `data:image/jpeg;base64,...`, используйте явный `DataUriBase64FileCast`
- если приходит `list<data-uri-string>`, используйте `Nested(type: Base64File::class, itemCast: DataUriBase64FileCast::class)`

Пример:
```php
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Casts\DataUriBase64FileCast;
use Brahmic\ApiSutra\VO\Files\Base64File;

#[Cast(DataUriBase64FileCast::class)]
public ?Base64File $photo = null;
```

`DataUriBase64FileCast`:
- понимает и чистый base64
- и data-uri с префиксом `data:...;base64,`
- в `Base64File` передаёт уже нормализованный base64 payload

## Архивы
Если ответы приходят архивом, можно подключить архивное расширение.
```php
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Extensions\Archive\ArchiveExtension;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    extensions: [new ArchiveExtension()],
);
```

Также доступны `FileResponse::isArchive()` и `FileResponse::asArchive()`.

По умолчанию (без `ArchiveExtension`) архив возвращается как обычный `FileResponse`.
Этого достаточно, если вы сами проверяете `isArchive()` и вызываете `asArchive()`.
`ArchiveExtension` нужен, когда вы хотите автоматическую обработку архивов
по `Content-Type` через handlers.

`ArchiveResponse` позволяет:
- `list()` / `get()` / `first()` / `find()` / `each()` / `extractAll()`

`ArchiveEntry` поддерживает:
- `contents()` / `stream()` / `saveTo()`

Пример работы с архивом:
```php
$file = (new DownloadFile(10))->send()->dataOrFail();
if ($file->isArchive()) {
    $archive = $file->asArchive();
    $entry = $archive->first();
    if ($entry !== null) {
        $entry->saveTo('/tmp/first.pdf');
    }
}
```

Нюансы:
- Для zip нужен `ext-zip`, для tar — `ext-phar`.
- Временные директории управляются через `ArchiveConfig` (см. ниже).

## Где детали
- `docs/guides/attributes/request.md` — `File`
- `docs/guides/attributes/response.md` — `Download`
- `docs/guides/client-config/archive.md`
