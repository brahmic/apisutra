# Файлы

## File (атрибут)
Атрибут для свойств запроса с файлами. Параметр format: FileFormat (Multipart, Binary, Base64). По умолчанию Multipart.

## FileInput
Value Object для файла на upload. Методы создания: fromPath(), tryFromPath() (не бросает, возвращает null), fromContent(), fromStream(). Методы: withMimeType(). tryFromPath() — для path-flow без исключений (валидация через validateCustom).

## FileFormat
Namespace: `Brahmic\ApiSutra\Enums\Http\FileFormat`.
Enum форматов отправки файла. Multipart — multipart/form-data (default). Binary — бинарное тело. Base64 — закодированный в JSON.

## Download (атрибут)
Атрибут для download-запросов. Меняет обработку response — вместо JSON-гидрации возвращается FileResponse.

## FileResponse
Результат download-запроса. Методы: stream(), content(), filename(), mimeType(), size(), saveTo().

## Base64File
Value Object для файла из response (base64-закодированный в JSON). SDK автоматически распознаёт тип и декодирует. Для массивов: #[Nested(type: Base64File::class)]. Методы: content(), stream(), size(), saveTo().

## saveTo()
Метод на download-запросе и Base64File/FileResponse. Сохраняет файл на диск. Для больших файлов — streaming без загрузки в память.
