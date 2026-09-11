# Extensions

## ExtensionInterface
Контракт расширения SDK. Методы: getName(), register(), boot(), checkDependencies(), isEnabled(). checkDependencies вызывается перед register; boot — lazy при первом использовании.

## ExtensionRegistry
Хранилище расширений per-client. Методы: register(), get(), has(), all(). Изоляция между разными Client — расширения не конфликтуют глобально.

## ExtensionContext
Контекст для регистрации компонентов расширения. Методы: registerCast(), registerHook(), registerResponseHandler(), registerAttributeHandler(). Параметр override для явной замены существующих handlers.

## ResponseHandlerInterface
Интерфейс обработчика response по MIME. Методы: supports(), handle(). Используется Extensions для специализированной обработки (архивы, XML, etc.).

## ArchiveExtension
Built-in расширение для работы с архивами (ZIP, TAR). Регистрирует handlers для archive MIME types. Lazy boot с проверкой наличия `ext-zip`/`ext-phar`.

## ArchiveResponse
Response-обёртка для архивов. Методы: list(), has(), get(), first(), find(), each(), extractAll(), getFormat(). Использует temp‑файл для открытия архива, очистка гарантируется при ошибках. Работа с файлами через ArchiveEntry.

## ArchiveEntry
Value Object файла в архиве. Свойства: name, size, compressedSize, isDirectory, modifiedAt. Методы: contents(), stream(), saveTo(). Читает из архива, открытого через temp‑файл.

## TempDirectoryProviderInterface
Внутренний контракт для создания/очистки temp‑файлов архивов. Используется для кастомного driver через DI.

## ExtensionConflictException
Исключение при попытке зарегистрировать handler на уже занятый MIME без флага override: true.

## ExtensionDisabledException
Исключение при попытке использовать отключённое расширение (failed checkDependencies).
