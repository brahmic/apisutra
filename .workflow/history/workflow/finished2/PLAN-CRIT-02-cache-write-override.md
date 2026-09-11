# План: исправить override enabled в CacheManager::storeCache

## Цель
Сделать `override['enabled']=false` корректным: кеш не должен записываться.

## Контекст
Файл: `packages/brahmic/apisutra/src/Pipeline/Cache/CacheManager.php`  
Проблема: при `overrideEnabled === false` сейчас устанавливается `writeEnabled = true`.

## Шаги
1) Исправить логику:
   - `overrideEnabled === false` → `writeEnabled = false`.
2) Проверить `checkCache()` на совместимость (readEnabled уже корректен).
3) Добавить минимальный тест или ручной кейс:
   - `->withoutCache()` не читает, но пишет? (по текущей логике read=false, write=true).
   - `override['enabled']=false` → нет записи.

## Критерии приёмки
- При `overrideEnabled=false` запись в кеш не происходит.
- Поведение чтения/записи согласовано с текущими правилами.

## Риски
- Изменение затронет сценарии, где ранее кеш записывался ошибочно.
