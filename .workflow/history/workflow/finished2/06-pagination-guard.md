# План 06: защита от бесконечной пагинации (P1)

## Проблема
`src/Pagination/Paginator.php` может зациклиться, если `hasMore` всегда `true` или курсор/страница не меняется.

## Рекомендованное решение
- Добавить guard: `maxPages`/`maxRequests` в `PaginationOptions`.
- Останавливать при отсутствии прогресса (cursor/page не меняются).
- Логировать причину остановки в debug/audit.

## Нюансы
- Для offset‑based прогресс = рост offset.
- Для cursor‑based сравнивать предыдущее значение cursor.
