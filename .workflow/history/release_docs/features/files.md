# Работа с файлами

Механизмы upload и download файлов.

## Upload

### Базовое использование

```php
#[Post('/documents')]
#[Returns(DocumentResponse::class)]
class UploadDocument extends AbstractRequest
{
    public string $title;
    
    #[File]
    public FileInput $file;
}

// Использование
$result = $client->documents()->upload(
    title: 'Договор',
    file: FileInput::fromPath('/tmp/contract.pdf'),
)->send();
```

SDK автоматически формирует `multipart/form-data`.

---

### FileInput

Value Object для представления файла на upload:

```php
// Из пути
FileInput::fromPath('/tmp/file.pdf')

// Из содержимого
FileInput::fromContent($bytes, 'file.pdf')

// Из stream
FileInput::fromStream($stream, 'file.pdf')

// С явным mime-type
FileInput::fromPath('/tmp/file.pdf')->withMimeType('application/pdf')
```

---

### Форматы отправки

**Multipart (default):**
```php
#[File]
public FileInput $file;
// → Content-Type: multipart/form-data
```

**Binary body:**
```php
#[File(format: FileFormat::Binary)]
public FileInput $file;
// → Content-Type: application/octet-stream (или из файла)
```

**Base64 в JSON:**
```php
#[File(format: FileFormat::Base64)]
public FileInput $file;
// → {"file": "JVBERi0xLjQK..."}
```

### FileFormat enum

Namespace: `Brahmic\ApiSutra\Enums\Http\FileFormat`.

```php
enum FileFormat: string
{
    case Multipart = 'multipart';  // multipart/form-data (default)
    case Binary = 'binary';        // application/octet-stream
    case Base64 = 'base64';        // base64 в JSON
}
```

---

### Несколько файлов

```php
#[Post('/documents/batch')]
class UploadDocuments extends AbstractRequest
{
    #[File]
    public array $files;  // array<FileInput>
}

$result = $client->documents()->uploadBatch(
    files: [
        FileInput::fromPath('/tmp/doc1.pdf'),
        FileInput::fromPath('/tmp/doc2.pdf'),
    ],
)->send();
```

---

## Download

### Базовое использование

```php
#[Get('/reports/{id}/download')]
#[Download]
class DownloadReport extends AbstractRequest
{
    public string $id;
}
```

Атрибут `#[Download]` меняет обработку response — вместо JSON-гидрации возвращается `FileResponse`.

---

### Получение файла

**Сохранить на диск (рекомендуется для больших файлов):**
```php
$client->reports()->download($id)
    ->saveTo('/tmp/report.pdf')
    ->send();
// Файл сохранён, в память не загружался
```

**Получить как stream:**
```php
$result = $client->reports()->download($id)->send();

$result->data->stream();      // StreamInterface (PSR-7)
$result->data->filename();    // Имя файла (из Content-Disposition)
$result->data->mimeType();    // MIME-тип
$result->data->size();        // Размер в байтах
```

**Получить содержимое (для небольших файлов):**
```php
$result = $client->reports()->download($id)->send();
$bytes = $result->data->content();  // string — весь файл в памяти
```

---

### FileResponse

Результат download-запроса:

```php
readonly class FileResponse
{
    public function stream(): StreamInterface;
    public function content(): string;
    public function filename(): ?string;
    public function mimeType(): ?string;
    public function size(): ?int;
    public function saveTo(string $path): void;
}
```

---

## Base64 в Response

Когда API возвращает файлы закодированные в JSON.

### Одиночное значение

```php
// Response: {"document": "JVBERi0xLjQK..."}

readonly class ApiResponse extends AbstractResponseDto
{
    public Base64File $document;  // SDK видит тип → декодирует base64
}

// Использование
$result->data->document->content();  // декодированные байты
$result->data->document->saveTo('/tmp/doc.pdf');
```

### Массив значений

```php
// Response: {"attachments": ["iVBORw0KGgo...", "iVBORw0KGgo..."]}

readonly class ApiResponse extends AbstractResponseDto
{
    #[Nested(type: Base64File::class)]
    public array $attachments;  // array<Base64File>
}

foreach ($result->data->attachments as $index => $file) {
    $file->saveTo("/tmp/{$index}.png");
}
```

### Вложенные объекты с файлами

```php
// Response: {"files": [{"name": "a.pdf", "data": "JVB..."}, ...]}

readonly class ApiResponse extends AbstractResponseDto
{
    #[Nested(type: FileDto::class)]
    public array $files;
}

readonly class FileDto extends AbstractDto
{
    public string $name;
    public Base64File $data;  // по типу
}
```

---

### Base64File

Value Object для файла из response (base64-закодированный):

```php
readonly class Base64File
{
    public function content(): string;           // декодированные байты
    public function stream(): StreamInterface;   // как поток
    public function size(): int;                 // размер
    public function saveTo(string $path): void;  // сохранить на диск
}
```

SDK автоматически распознаёт тип `Base64File` и декодирует строку из JSON.

---

## Архивы

Поддержка работы с архивами через `ArchiveExtension` (built-in).

### Детекция

```php
$response = $client->files()->download($id)->send();

// Проверка — архив ли это
if ($response->data->isArchive()) {
    $archive = $response->data->asArchive();
    // ...
}
```

Детекция по MIME-type или magic bytes.

### ArchiveResponse

```php
$archive = $response->data->asArchive();

// Список файлов (метаданные)
$entries = $archive->list();  // array<ArchiveEntry>

// Получить entry по имени
$entry = $archive->get('report.pdf');

// Сохранить на диск (читает напрямую из архива)
$entry->saveTo('/storage/reports/report.pdf');

// Прочитать в память
$content = $entry->contents();

// Получить stream
$stream = $entry->stream();

// Извлечь всё в папку
$archive->extractAll('/storage/extracted/');

// Прочитать файл напрямую из архива
$content = $archive->read('report.pdf');

// Получить stream напрямую
$resource = $archive->stream('report.pdf');

// Поиск по условию
$xml = $archive->find(fn($e) => str_ends_with($e->name, '.xml'));
```

### ArchiveEntry

Представляет файл внутри архива:

```php
// Метаданные
$entry->name           // имя файла
$entry->size           // размер (распакованный)
$entry->compressedSize // размер (сжатый)
$entry->isDirectory    // директория?
$entry->modifiedAt     // время модификации

// Извлечение (читает из архива)
$entry->contents()     // в память (string)
$entry->stream()       // StreamInterface
$entry->saveTo($path)  // на диск
```

### Требования

`ArchiveExtension` требует:
- `ext-zip` для ZIP
- `ext-phar` для TAR (опционально)

Если нет ни `ext-zip`, ни `ext-phar` — расширение отключается.
Если нужное расширение для формата отсутствует — выбрасывается `ConfigurationException`.

Для работы используются временные файлы. Настройки:
`archive` (ArchiveConfig). Подробности — `architecture/06-config.md`.

См. [Extensions](./extensions.md)

---

## Резюме

| Задача | Решение |
|--------|---------|
| Upload файл | `#[File]` + `FileInput` |
| Upload несколько | `array<FileInput>` |
| Upload как base64 | `FileInput` + `FileFormat::Base64` |
| Download файл | `#[Download]` → `FileResponse` |
| Download на диск | `->saveTo(path)->send()` |
| Base64 в response | Тип `Base64File` — по типу свойства |
| Массив base64 | `#[Nested(type: Base64File::class)]` |
| Работа с архивами | `->asArchive()` + `ArchiveExtension` |
