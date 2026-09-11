# План 11: тесты cursor и edge cases пагинации (P0)

## Проблема
Нет тестов cursor‑based пагинации, пустых результатов и `failStrategy`.

## Рекомендованное решение
- Добавить тесты `Paginator` для cursor‑based сценариев (обновление cursor).
- Тесты на пустые результаты и `hasMore=false`.
- Тесты `FailStrategy::FailFast` vs `FailAll`.

## Нюансы
- Использовать фикстуру `tests/Fixtures/provider-b/30-search-empty.json`.
