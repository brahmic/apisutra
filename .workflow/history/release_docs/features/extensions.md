# Extensions — Система расширений

## Обзор

SDK поддерживает модульную архитектуру через Extensions. Расширения позволяют:
- Добавлять обработку специфичных форматов (архивы, XML, CSV)
- Регистрировать кастомные Casts, Hooks, Handlers
- Расширять функционал без модификации ядра

**Scope:** Расширения привязаны к конкретному Client, не глобально.

---

## Регистрация

### Через ClientConfig

```php
$client = new MyClient(
    config: new ClientConfig(
        baseUrl: 'https://api.example.com',
        extensions: [
            new ArchiveExtension(),
            new MyCustomExtension(),
        ],
    ),
);
```

### Runtime

```php
$client->registerExtension(new ArchiveExtension());
```

---

## Lifecycle

### Register Phase

Вызывается сразу при добавлении extension. Декларативная фаза:
- Проверка зависимостей (`checkDependencies()`)
- Регистрация handlers, casts, hooks
- Без side effects
- Результат можно кешировать

### Boot Phase

Вызывается lazy — при первом использовании extension:
- Инициализация с реальным конфигом
- Подключение к внешним сервисам (если нужно)

Первым использованием считается вызов зарегистрированного компонента:
handler/каста/хука, зарегистрированного через ExtensionContext.

```
1. Client создаётся
2. extension.checkDependencies()
3. extension.register() — сразу
4. ...время идёт...
5. Приходит ZIP response
6. SDK ищет handler → находит в ArchiveExtension
7. extension.boot() — если ещё не booted
8. Handler обрабатывает response
```

---

## ExtensionInterface

```php
namespace Brahmic\ApiSutra\Contracts;

interface ExtensionInterface
{
    /**
     * Уникальное имя расширения
     */
    public function getName(): string;
    
    /**
     * Phase 1: декларация компонентов
     * Вызывается сразу при добавлении
     */
    public function register(ExtensionContext $context): void;
    
    /**
     * Phase 2: инициализация
     * Вызывается lazy, при первом использовании
     */
    public function boot(ClientConfig $config): void;
    
    /**
     * Проверка зависимостей (php extensions, etc.)
     * Вызывается перед register()
     */
    public function checkDependencies(): void;
    
    /**
     * Статус после boot
     */
    public function isEnabled(): bool;
}
```

---

## ExtensionContext

Контекст для регистрации компонентов:

```php
namespace Brahmic\ApiSutra\Extensions;

class ExtensionContext
{
    /**
     * Регистрация Cast
     */
    public function registerCast(string $type, CastInterface $cast): void;
    
    /**
     * Регистрация Hook
     */
    public function registerHook(
        Hook $type,
        HookInterface $hook,
        HookPriority $priority = HookPriority::Normal,
    ): void;
    
    /**
     * Регистрация Response Handler по MIME
     */
    public function registerResponseHandler(
        string $mime,
        ResponseHandlerInterface $handler,
        bool $override = false,
    ): void;
    
    /**
     * Регистрация Attribute Handler
     */
    public function registerAttributeHandler(
        string $attributeClass,
        AttributeHandlerInterface $handler,
    ): void;
}
```

---

## Конфликты и Override

При регистрации handler на уже занятый MIME:

```php
// По умолчанию — исключение
$context->registerResponseHandler('application/zip', $handler);
// ExtensionConflictException: Handler for 'application/zip' already registered

// Явное переопределение
$context->registerResponseHandler(
    mime: 'application/zip',
    handler: $myHandler,
    override: true,  // OK, заменяет существующий
);
```

**Конфликты возможны только внутри одного Client** — разные клиенты изолированы.

---

## Проверка зависимостей

```php
public function checkDependencies(): void
{
    if (!extension_loaded('zip') && !extension_loaded('phar')) {
        $this->enabled = false;
    }
}
```

**Поведение:**
- Extension отключается (`isEnabled() = false`), если нет ни `ext-zip`, ни `ext-phar`
- При попытке использовать → `ExtensionDisabledException`

---

## Built-in Extensions

### ArchiveExtension

Поддержка работы с архивами (ZIP, TAR).

```php
use Brahmic\ApiSutra\Extensions\Archive\ArchiveExtension;

$client = new MyClient(
    config: new ClientConfig(
        extensions: [new ArchiveExtension()],
    ),
);
```

**Возможности:**
- Автодетекция архива по MIME / magic bytes
- Ленивая распаковка
- Поддержка ZIP, TAR, TAR.GZ

---

## Работа с архивами

### Детекция

```php
$response = $client->files()->download($id)->send();

if ($response->data->isArchive()) {
    $archive = $response->data->asArchive();
    // ...
}
```

### ArchiveResponse API

```php
$archive->list(): array<ArchiveEntry>      // Список файлов (метаданные)
$archive->has(string $name): bool          // Проверка наличия
$archive->get(string $name): ?ArchiveEntry // Получить entry по имени
$archive->first(): ?ArchiveEntry           // Первый файл
$archive->find(Closure $fn): ?ArchiveEntry // Поиск по условию
$archive->each(Closure $fn): void          // Итерация по entries
$archive->extractAll(string $path): void   // Извлечь всё в папку
$archive->getFormat(): string              // zip, tar, tar.gz
```

### ArchiveEntry (файл в архиве)

```php
// Метаданные
$entry->name: string
$entry->size: int
$entry->compressedSize: int
$entry->isDirectory: bool
$entry->modifiedAt: DateTimeInterface

// Извлечение (читает из архива)
$entry->contents(): string           // В память
$entry->stream(): StreamInterface    // Stream из архива
$entry->saveTo(string $path): void   // На диск
```

**Важно:** Для открытия архива используется временный файл. Настройки:
`archive` (ArchiveConfig). Подробности — `architecture/06-config.md`.

### Пример использования

```php
$archive = $response->data->asArchive();

// Сохранить конкретный файл
$entry = $archive->get('report.pdf');
$entry->saveTo('/storage/reports/report.pdf');

// Прочитать в память
$content = $archive->first()->contents();

// Найти и получить stream
$xml = $archive->find(fn($e) => str_ends_with($e->name, '.xml'));
if ($xml) {
    $stream = $xml->stream();
}

// Извлечь всё в папку
$archive->extractAll('/storage/extracted/');

// Итерация по метаданным
$archive->each(function (ArchiveEntry $entry) {
    echo "{$entry->name}: {$entry->size} bytes\n";
});
```

---

## Кастомные Extensions

### Создание

```php
namespace App\Extensions;

use Brahmic\ApiSutra\Contracts\Extensions\ExtensionInterface;
use Brahmic\ApiSutra\Extensions\ExtensionContext;
use Brahmic\ApiSutra\Config\ClientConfig;

class MyExtension implements ExtensionInterface
{
    private bool $enabled = true;
    private bool $booted = false;
    
    public function getName(): string
    {
        return 'my-extension';
    }
    
    public function register(ExtensionContext $context): void
    {
        $context->registerCast(Money::class, new MoneyCast());
        $context->registerHook(Hook::AfterResponse, new MyLogHook());
    }
    
    public function boot(ClientConfig $config): void
    {
        if ($this->booted) return;
        
        $this->checkDependencies();
        
        if ($this->enabled) {
            // Инициализация...
        }
        
        $this->booted = true;
    }
    
    public function checkDependencies(): void
    {
        // Проверки...
    }
    
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
```

### Регистрация

```php
$client->registerExtension(new MyExtension());
```

---

## Порядок выполнения

### Response Handlers

Приоритет поиска:
1. Точный MIME: `application/zip`
2. Wildcard: `application/*`
3. Default handler

### Hooks

Порядок определяется `HookPriority`:
- `First` — выполняется первым
- `Normal` — стандартный
- `Last` — выполняется последним

---

## Резюме

| Компонент | Назначение |
|-----------|------------|
| `ExtensionInterface` | Контракт расширения |
| `ExtensionContext` | Регистрация компонентов |
| `ExtensionRegistry` | Хранение extensions в Client |
| `ArchiveExtension` | Built-in: работа с архивами |
| `ArchiveResponse` | API для работы с архивом |
