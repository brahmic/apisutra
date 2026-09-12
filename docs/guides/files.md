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
Binary и multipart передаются потоками. Начало файла фиксируется по текущей позиции
при подготовке выполнения; адаптер не получает байты до этой позиции даже при rewind.
Повторный `send()` начинает новое выполнение с текущей позиции исходника: если нужен
весь файл, перемотайте его заранее. `FileInput::fromPath()` открывает файл с начала.

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
- `close()` — закрыть ручку, созданную `fromPath()`/`fromContent()`; заимствованный
  через `fromStream()` поток не закрывается. Копии `withMimeType()` разделяют ручку.

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
- `saveTo()` / `close()`

### Потоковый download без дополнительных настроек

`#[Download]` автоматически получает ответ во временный файл на диске. В памяти
находятся только порции данных; `FileResponse` доступен после завершения HTTP.
Размер буфера и каталог настраивать не нужно. В системном временном каталоге
должно быть достаточно места для ответа и одновременно сохраняемых результатов.

`stream()` возвращает читаемый поток с начальной позицией 0, `size()` — фактическое
число полученных байтов, включая корректный размер пустого файла 0. `content()`
явно читает весь файл в строку и требует соответствующей памяти. `asArchive()`
также материализует содержимое; потоковый download не делает разбор архивов потоковым.

### Сохранение сразу в путь или поток

```php
$file = (new DownloadFile(10))
    ->withDownloadTo('/tmp/report.pdf')
    ->send()
    ->dataOrFail();
```

Новый `withDownloadTo()` защищает существующий файл: замена разрешается только через
`overwrite: true`. Принимается локальный путь; каталог должен существовать.
Stream wrappers, конечные symlink и каталог вместо файла отклоняются до HTTP.
Имя из `Content-Disposition` не используется как путь назначения.

Сначала SDK получает файл во временный файл рядом с назначением. После окончательного
принятого 2xx, hooks и проверки бюджета он атомарно публикует результат. Неудачный
HTTP, отказ hook или retry не затирают прежний файл и не смешивают тела попыток.
Публикация без overwrite не заменяет и файл, появившийся во время запроса.
Если файловая система не поддерживает атомарную операцию, возвращается ошибка.
Это не гарантия сохранности при аппаратном сбое.

```php
use GuzzleHttp\Psr7\Utils;

$sink = Utils::streamFor(fopen('/tmp/report-copy.pdf', 'w+b'));
try {
    $file = (new DownloadFile(10))->withDownloadTo($sink)->send()->dataOrFail();
} finally {
    $sink->close();
}
```

Для пользовательского `StreamInterface` требуется writable поток. SDK копирует
только окончательный успешный ответ с текущей позиции приёмника, не перематывает,
не усекает хвост и не закрывает его. Write-only и non-seekable приёмники допустимы;
`overwrite` к потоку неприменим. Если ошибка возникла при финальном копировании,
приёмник может содержать часть файла. Ошибка сообщает `bytesWritten` и `partial`;
HTTP из-за локальной ошибки записи не повторяется.

Результат остаётся `FileResponse` с собственным читаемым потоком, в том числе для
write-only приёмника. `withoutDownloadTo()` снимает цель и возвращает обычный download.
Эти runtime-опции применимы только к запросам с `#[Download]` и не наследуются
дочерними запросами.

Существующий `FileResponse::saveTo()` сохраняет разрешённую перезапись, но тоже
проверяет запись и публикует файл атомарно. Для seekable источника читает с начала;
для non-seekable — с текущей позиции. `isArchive()` не читает magic bytes из
non-seekable потока, чтобы не потерять первые байты; MIME остаётся доступным.

### Владение ресурсами, ошибки и диагностика

SDK не закрывает пользовательские upload/sink потоки. Ручки `FileInput`, созданные
фабриками SDK, остаются доступными после отправки, пока их владелец жив; для раннего
освобождения используйте `close()`. Повторное использование требует контроля позиции.

`FileResponse` и raw response разделяют поток. Освобождение одной ссылки не закрывает
его для другой. Явный `FileResponse::close()` закрывает общий поток; последний владелец
также освобождает временный файл. Конечный сохранённый файл при этом не удаляется.
В сохранённом ошибочном raw-ответе поток остаётся доступным для диагностики.

HTTP-таймауты охватывают получение тела; общий бюджет также проверяется при копировании
и перед публикацией. Отдельный блокирующий вызов произвольного пользовательского потока
SDK не может принудительно прервать. Локальные ошибки имеют `file_transfer_error`,
истечение бюджета — `timeout`. Ошибка финального logger после сохранения не отменяет
успех файловой операции.

Файловые upload/download исключены из HTTP-кеша автоматически. Явное включение
`withCache()`/`#[Cache]` даёт `configuration_error` до HTTP; `withoutCache()` снимает
конфликт. Это относится и к upload с `FileInput` в Base64. Обычные JSON-строки
не считаются файлами только из-за похожего содержимого.

Debug и recorder показывают метаданные потока, не читают файл и не меняют его позицию.
Даже `requestDebug(false)` не создаёт строковую копию. Для воспроизведения файлов
используйте [ручную файловую fixture](testing.md#файловые-ответы).

### Замена файлового тела в hook

`withBody($json)` заменяет поток строкой, `withStream($stream)` выбирает новый поток,
`withoutBody()` удаляет всё исходящее тело. При multipart → JSON явно задайте
`Content-Type: application/json`. Старые Content-Length/Transfer-Encoding убираются
автоматически, исходный поток остаётся открытым. Очистка upload не включает HTTP-кеш
и не сбрасывает download target, назначение или общий бюджет. Поток, добавленный hook,
требует `FileStreamingInterface` даже при исходном запросе без файла.
Полный контракт и пример — [управление телом](transport.md#замена-и-очистка-тела-preparedrequest).

### Совместимость при обновлении

- Binary находится в `PreparedRequest.stream`, `body = null`; учитывайте это
  в собственных hooks/адаптерах. Ненулевая позиция теперь сохраняется.
- Non-seekable binary больше не получает строковую копию для retry: повтор запрещён.
- У download `ProviderResponse.body = null`, полный ответ доступен в `stream`.
  `json()`/`jsonStrict()` на таком ответе отклоняются вместо неявного чтения файла.
  Для обычных JSON/text ответов строковый контракт сохранён.
- `errorMessage()` читает JSON-сообщение потокового ответа только если всё тело
  укладывается в 64 KiB, восстанавливая позицию. Для большего тела используется
  `HTTP <status>`; полный raw-поток сохраняется.
- Явный HTTP cache файлов больше не поддерживается. Сторонним транспортам,
  PSR-клиентам и retry handler нужен [контракт FileStreamingInterface](transport.md#потоковые-файлы).
- Base64 JSON, явные `content()`/`asArchive()` и обычные ответы без `#[Download]`
  остаются операциями с полной материализацией. Универсальный лимит JSON не вводится.

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
