# Распаковка архивов

Настройки архивирования файлов через `ArchiveConfig`.

## Базовая настройка
```php
use Brahmic\ApiSutra\Config\ArchiveConfig;
use Brahmic\ApiSutra\Config\ClientConfig;

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    archive: new ArchiveConfig(driver: 'auto'),
);
```

## Параметры ArchiveConfig
- `driver`: `native` | `spatie` | `auto`
- `tempDir`: базовая директория для временных файлов
- `maxSize`: лимит размера архива (в байтах)
- `tempProvider`: собственный провайдер временных директорий

Поведение по умолчанию:
- без `ArchiveConfig` используется `native`
- `auto` выберет `spatie`, если пакет установлен, иначе `native`

`maxSize` проверяется при подготовке временного файла. Если архив превышает лимит,
будет `ConfigurationException`.

`tempProvider` имеет приоритет над `driver` и `tempDir`.

## Кастомный tempProvider
Используйте, если нужно строго контролировать директорию, cleanup,
или окружение ограничено (read-only, нестандартные tmp‑пути).
Если требований нет — достаточно `native`/`auto`.

```php
use Brahmic\ApiSutra\Extensions\Archive\Temp\TempDirectoryProviderInterface;

final class ProviderTempDirectory implements TempDirectoryProviderInterface
{
    public function createTempFile(?string $suffix = null): string
    {
        $name = $suffix ? 'archive_' . $suffix : 'archive.tmp';
        return sys_get_temp_dir() . '/' . $name;
    }

    public function cleanup(string $path): void
    {
        @unlink($path);
    }
}

$config = $config->with(
    archive: new ArchiveConfig(
        tempProvider: new ProviderTempDirectory(),
        maxSize: 50 * 1024 * 1024,
    ),
);
```

## Зависимости
- `driver = spatie` требует пакет `spatie/temporary-directory`
- `auto` выберет spatie при наличии, иначе native

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
