# Приложения к aud-001

Материалы [аудита от 2026-09-11](../aud-001-readme.md).
Сохранены из временных файлов, чтобы сценарии и исходные результаты не потерялись.
Пути загрузки зависимостей сделаны переносимыми; входы сценариев сохранены.

## Состав

- [reproduce.php](reproduce.php) — локальные сценарии с имитацией HTTP и stores.
- [results-2026-09-11.jsonl](results-2026-09-11.jsonl) — исходные 17 результатов.
- [runtime-smoke.php](runtime-smoke.php) — минимальный запрос и DTO в отдельной
  установке; выводит также наличие Illuminate, Guzzle HTTP Client и Pest.

Скрипты используют искусственные токены и домен `audit.invalid`; HTTP handler
и transport подменены. Это диагностические сценарии, а не тестовый набор:
завершение с кодом 0 не означает отсутствие обнаруженных проблем.
После исправлений сравнивайте новый вывод с историческим; исходный JSONL сохраняйте.

## Запуск сценариев

Из корня пакета с установленными dev-зависимостями:

```bash
php .workflow/audit/aud-001-architecture-code-2026-09-11/artifacts/reproduce.php
```

Для проверки без dev-зависимостей подготовьте отдельную копию исходников
и Composer-файлов. Следующие команды не меняют установленный `vendor` проекта:

```bash
audit_runtime_dir="$(mktemp -d)"
cp composer.json composer.lock "$audit_runtime_dir/"
cp -R src "$audit_runtime_dir/"
composer install --working-dir="$audit_runtime_dir" --no-dev --no-interaction --no-progress
php .workflow/audit/aud-001-architecture-code-2026-09-11/artifacts/runtime-smoke.php "$audit_runtime_dir"
```

Ожидается `success`, `dto_id=42` и `false` для трёх проверяемых dev-классов.
Такой smoke проверяет runtime из скопированных исходников; установка из
подготовленного релизного архива остаётся отдельной проверкой F14.

## Связь результатов с находками

| Поле `case` | Находка |
| --- | --- |
| `cache_auth_scope`, `literal_query`, `ordered_query` | F01 |
| `redacted_debug_url` | F02 |
| `transport_timeouts` | F03 |
| `async_dispatch_before_wait` | F04, принятое ограничение |
| `refresh_lock_release` — два варианта store | F05 |
| `psr16_rate_limit_key` | F06 |
| `client_container_validation` | F07 |
| `network_exception_retry` | F08 |
| `integer_overflow_hydration`, `float_overflow_hydration` | F09 |
| `invalid_json_with_declared_dto` | F10 |
| `hydration_error_classification` | F11 |
| `cache_ttl_renewed_on_read` | F12 |
| `quickstart_container_resolution` | F13 |

F14 подтверждён отдельной проверкой локального Composer archive; его состав
зафиксирован в аудите. F15–F16 основаны на сверке кода и документации.
R01 оставлен рекомендацией к будущей проверке; эти скрипты его не проверяют.
