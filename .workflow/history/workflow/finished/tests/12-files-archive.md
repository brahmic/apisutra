# Files & Archive — план тестирования

## Scope
- `FileInput`, `FileResponse`, `Base64File`
- `ArchiveConfig`, temp files handling

## Invariants
- FileInput корректно сериализуется в multipart.
- FileResponse корректно сохраняет контент/metadata.
- Временные файлы создаются/удаляются по правилам.

## Unit tests
- `FileInput` с mime/filename.
- `Base64File` декодируется корректно.

## Integration tests
- Upload с `#[File]` → подготовка multipart.
- Download с `#[Download]` → `FileResponse`.
- Archive‑процесс с temp‑директорией.

## Edge cases
- Пустой файл.
- Неверный base64.

## Fixtures/Mocks
- Небольшие бинарные фикстуры (pdf/zip).
- Temp dir provider (stub).

## Priority
- P0: upload/download.
