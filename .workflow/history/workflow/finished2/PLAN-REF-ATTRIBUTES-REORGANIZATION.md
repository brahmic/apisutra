# План: Реорганизация структуры Attributes

## Цель
Разделить файлы с множественными классами атрибутов на отдельные файлы и организовать по тематическим папкам (принцип "one class per file").

## Текущее состояние

### Файлы с множественными классами:
1. **ConfigAttributes.php** — 8 классов
2. **DtoAttributes.php** — 5 классов
3. **HookAttributes.php** — 4 класса
4. **HttpAttributes.php** — 5 классов
5. **MappingAttributes.php** — 6 классов
6. **ResponseAttributes.php** — 2 класса

### Файлы с единственным классом:
- AuthScope.php ✓
- AttributeContext.php ✓ (служебный)
- AttributeMetadataCache.php ✓ (служебный)
- AttributeRegistry.php ✓ (служебный)

**Итого:** 10 файлов → 30 классов атрибутов + 3 служебных класса

---

## Целевая структура (Вариант 1)

```
Attributes/
├── Http/                    # HTTP методы и маршрутизация
│   ├── Get.php
│   ├── Post.php
│   ├── Put.php
│   ├── Patch.php
│   └── Delete.php
│
├── Request/                 # Параметры запроса
│   ├── Path.php
│   ├── Query.php
│   ├── Body.php
│   ├── Header.php
│   ├── File.php
│   ├── Ignore.php
│   └── AuthScope.php       # перенести из корня
│
├── Response/                # Обработка ответа
│   ├── Returns.php
│   └── Download.php
│
├── DataTransfer/            # DTO и маппинг
│   ├── From.php
│   ├── Cast.php
│   ├── Nested.php
│   ├── Validate.php
│   └── Label.php
│
├── Behavior/                # Поведение запроса
│   ├── Cache.php
│   ├── NoAuth.php
│   ├── Retry.php
│   ├── Timeout.php
│   ├── RateLimit.php
│   ├── Idempotent.php
│   ├── Execution.php
│   └── Pagination.php
│
├── Hooks/                   # Хуки жизненного цикла
│   ├── BeforeSend.php
│   ├── AfterResponse.php
│   ├── BeforeHydrate.php
│   └── AfterHydrate.php
│
└── [Служебные в корне]     # Инфраструктура
    ├── AttributeContext.php
    ├── AttributeMetadataCache.php
    └── AttributeRegistry.php
```

**Итого:** 6 папок, 34 файла (30 атрибутов + 3 служебных + 1 корневой AuthScope перемещён)

---

## План работ

### Этап 1: Создание структуры папок
```bash
mkdir -p src/Attributes/{Http,Request,Response,DataTransfer,Behavior,Hooks}
```

### Этап 2: Разделение HttpAttributes.php (5 классов)
**Источник:** `src/Attributes/HttpAttributes.php`  
**Namespace:** `Brahmic\ApiSutra\Attributes\Http`

| Класс   | Целевой файл                     |
|---------|----------------------------------|
| Get     | src/Attributes/Http/Get.php     |
| Post    | src/Attributes/Http/Post.php    |
| Put     | src/Attributes/Http/Put.php     |
| Patch   | src/Attributes/Http/Patch.php   |
| Delete  | src/Attributes/Http/Delete.php  |

### Этап 3: Разделение MappingAttributes.php (6 классов)
**Источник:** `src/Attributes/MappingAttributes.php`  
**Namespace:** `Brahmic\ApiSutra\Attributes\Request`

| Класс  | Целевой файл                        |
|--------|-------------------------------------|
| Path   | src/Attributes/Request/Path.php    |
| Query  | src/Attributes/Request/Query.php   |
| Body   | src/Attributes/Request/Body.php    |
| Header | src/Attributes/Request/Header.php  |
| File   | src/Attributes/Request/File.php    |
| Ignore | src/Attributes/Request/Ignore.php  |

### Этап 4: Перенос AuthScope.php
**Источник:** `src/Attributes/AuthScope.php`  
**Namespace:** `Brahmic\ApiSutra\Attributes\Request`  
**Целевой файл:** `src/Attributes/Request/AuthScope.php`

### Этап 5: Разделение ResponseAttributes.php (2 класса)
**Источник:** `src/Attributes/ResponseAttributes.php`  
**Namespace:** `Brahmic\ApiSutra\Attributes\Response`

| Класс    | Целевой файл                           |
|----------|----------------------------------------|
| Returns  | src/Attributes/Response/Returns.php   |
| Download | src/Attributes/Response/Download.php  |

### Этап 6: Разделение DtoAttributes.php (5 классов)
**Источник:** `src/Attributes/DtoAttributes.php`  
**Namespace:** `Brahmic\ApiSutra\Attributes\DataTransfer`

| Класс    | Целевой файл                                |
|----------|---------------------------------------------|
| From     | src/Attributes/DataTransfer/From.php       |
| Cast     | src/Attributes/DataTransfer/Cast.php       |
| Nested   | src/Attributes/DataTransfer/Nested.php     |
| Validate | src/Attributes/DataTransfer/Validate.php   |
| Label    | src/Attributes/DataTransfer/Label.php      |

### Этап 7: Разделение ConfigAttributes.php (8 классов)
**Источник:** `src/Attributes/ConfigAttributes.php`  
**Namespace:** `Brahmic\ApiSutra\Attributes\Behavior`

| Класс      | Целевой файл                               |
|------------|---------------------------------------------|
| Cache      | src/Attributes/Behavior/Cache.php          |
| NoAuth     | src/Attributes/Behavior/NoAuth.php         |
| Retry      | src/Attributes/Behavior/Retry.php          |
| Timeout    | src/Attributes/Behavior/Timeout.php        |
| RateLimit  | src/Attributes/Behavior/RateLimit.php      |
| Idempotent | src/Attributes/Behavior/Idempotent.php     |
| Execution  | src/Attributes/Behavior/Execution.php      |
| Pagination | src/Attributes/Behavior/Pagination.php     |

### Этап 8: Разделение HookAttributes.php (4 класса)
**Источник:** `src/Attributes/HookAttributes.php`  
**Namespace:** `Brahmic\ApiSutra\Attributes\Hooks`

| Класс          | Целевой файл                                |
|----------------|---------------------------------------------|
| BeforeSend     | src/Attributes/Hooks/BeforeSend.php        |
| AfterResponse  | src/Attributes/Hooks/AfterResponse.php     |
| BeforeHydrate  | src/Attributes/Hooks/BeforeHydrate.php     |
| AfterHydrate   | src/Attributes/Hooks/AfterHydrate.php      |

### Этап 9: Обновление импортов
Найти и заменить все `use` statements в кодовой базе:

**Поиск паттернов:**
```bash
grep -r "use Brahmic\\ApiSutra\\Attributes\\" --include="*.php" | grep -v "Attributes\\(Http\\|Request\\|Response\\|DataTransfer\\|Behavior\\|Hooks)"
```

**Примеры замен:**
- `use Brahmic\ApiSutra\Attributes\Get;` → `use Brahmic\ApiSutra\Attributes\Http\Get;`
- `use Brahmic\ApiSutra\Attributes\Path;` → `use Brahmic\ApiSutra\Attributes\Request\Path;`
- `use Brahmic\ApiSutra\Attributes\Cache;` → `use Brahmic\ApiSutra\Attributes\Behavior\Cache;`
- `use Brahmic\ApiSutra\Attributes\Returns;` → `use Brahmic\ApiSutra\Attributes\Response\Returns;`
- `use Brahmic\ApiSutra\Attributes\Cast;` → `use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;`
- `use Brahmic\ApiSutra\Attributes\BeforeSend;` → `use Brahmic\ApiSutra\Attributes\Hooks\BeforeSend;`
- `use Brahmic\ApiSutra\Attributes\AuthScope;` → `use Brahmic\ApiSutra\Attributes\Request\AuthScope;`

### Этап 10: Обновление autoload
```bash
composer dump-autoload -q
```

### Этап 11: Удаление старых файлов
После подтверждения работоспособности:
- ConfigAttributes.php
- DtoAttributes.php
- HookAttributes.php
- HttpAttributes.php
- MappingAttributes.php
- ResponseAttributes.php

### Этап 12: Проверка тестов
```bash
php vendor/bin/pest packages/brahmic/apisutra/tests/Unit --compact
php vendor/bin/pest packages/brahmic/apisutra/tests --compact
```

### Этап 13: Проверка линтера
```bash
# Проверить все новые файлы атрибутов
```

---

## Контрольный чек-лист

- [ ] Создана структура папок (6 папок)
- [ ] Разделены все 30 атрибутов на отдельные файлы
- [ ] Перенесён AuthScope.php в Request/
- [ ] Обновлены все namespace во всех новых файлах
- [ ] Найдены и обновлены все use statements в кодовой базе
- [ ] Обновлён composer autoload
- [ ] Все Unit тесты проходят
- [ ] Все Integration тесты проходят (если есть)
- [ ] Линтер не выдаёт ошибок
- [ ] Удалены старые файлы с множественными классами (6 файлов)
- [ ] Проверена работоспособность на реальных провайдерах

---

## Ожидаемый результат

### До:
- 10 файлов
- 6 файлов с множественными классами
- Плоская структура

### После:
- 34 файла
- Каждый файл = 1 класс ✓
- 6 тематических папок
- Чистая семантика и навигация

---

## Риски и митигация

| Риск | Вероятность | Митигация |
|------|-------------|-----------|
| Пропущенные импорты | Средняя | Полный grep по кодовой базе, запуск всех тестов |
| Кеш PHPStorm не обновился | Низкая | Invalidate Caches and Restart |
| Забыли dump-autoload | Низкая | Включить в план как обязательный шаг |
| Сломались тесты провайдеров | Низкая | Запустить тесты всех провайдеров после миграции |

---

## Альтернативные варианты структуры

Если Вариант 1 не подходит, см. альтернативные варианты 2 и 3 в исходном обсуждении.

---

## Дата создания
2026-02-03

## Статус
🔴 Планирование
