# Файлы и архивы

## Файлы

### Upload файла
```php
$file = FileInput::fromPath('/path/to/report.pdf');
$result = $client->files()->upload($file)->dataOrFail();
```
Тип: `UploadResultDto`

### Download файла
```php
$resolved = $client->files()->download($id)->resolved();
$file = $resolved->data();
```
Тип: `ResolvedResult<FileResponse>`

## Архивы

### Архив в ответе
```php
$archive = $client->reports()->archive($range)->dataOrFail();
```
Тип: `ArchiveResponse`

## Открыть скачанный архив
```php
$file = (new DownloadFile(10))->send()->dataOrFail();
if ($file->isArchive()) {
    $archive = $file->asArchive();
    $first = $archive->first();
}
```
