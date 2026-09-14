# Проверка документации после 028, 030 и 031

- Дата: 2026-09-15.
- Основание: просьба владельца рекомендовать `_extra` и повторно проверить полноту описания изменений.
- Область: публичная документация реализованных 028/030/031; перестройка по 027 не выполняется.

Повторная сверка выявила пропуски за пределами основных руководств: методология SDK
описывала входные правила только через профиль, таблица casts не включала внешний
набор, глоссарий не содержал новых API и относил отсутствие token при ожидании к
ошибке конфигурации. Обзоры кеша, naming и ошибок, а также чеклист SDK требовали
уточнений. Эти места исправлены; два несовпадающих якоря в публичных ссылках устранены.

## Имя приёмника

В публичном примере используется `public array $_extra = []` и `extras('_extra')`.
Это рекомендация по имени, а не изменение API: receiver остаётся настраиваемым.
Исходные `extra` и `_extra`, не прочитанные другими правилами, сохраняются внутри
приёмника. Пример коллизии добавлен в гайд и его существующую исполняемую проверку.
Исторические контракты, фикстуры и результаты с `extra` сохранены: они по-прежнему
показывают допустимое имя. Код `src/` не изменён.

## Покрытие публичного поведения

Таблица сверена с принятыми контрактами и приёмкой трёх планов, публичными сигнатурами
и реализацией входов. Полное описание остаётся в тематических руководствах;
глоссарий, методология и технические обзоры дают определения и ссылки.

| План и поведение | Основной публичный источник |
| --- | --- |
| 031: независимые object defaults, момент вычисления, сохранение намеренно общих объектов | [Defaults DTO](../../../docs/guides/dto.md#значения-по-умолчанию-и-изоляция-объектов) |
| 031: аргументы атрибутов, повторные вызовы, cache on/off, прежние части запроса с Cast | [Аргументы Cast](../../../docs/guides/casts.md#объектные-аргументы-атрибутов), [кеш метаданных](../../../docs/technical/attributes.md#резолв-и-кеш) |
| 030: resolver, контекст всех входов, Pending/Ready/Failed, режимы, отсутствие legacy-эвристики | [Provider Async Await](../../../docs/guides/provider-async-await.md), [ContinuationResult](../../../docs/guides/attributes/response.md#continuationresult) |
| 030: ошибки финала, token, maxAttempts/attempts, последний ответ, cached awaitAs, передача гидратора | [Provider Async Await](../../../docs/guides/provider-async-await.md), [глоссарий результатов](../../../docs/glossary/results.md) |
| 028 A: immutable API, exact class, конфликты атрибутов, профили и policy, with(null), standalone без Laravel | [Правила и конфигурация](../../../docs/guides/hydration-rules.md#правила-и-проверка-конфигурации), [ClientConfig](../../../docs/guides/client-config/serialization.md) |
| 028 A: strict, int → float, unions, scalar lists, required/null/inputShape, defaults и ограничения literal | [Strict](../../../docs/guides/hydration-rules.md#policy-и-строгие-типы), [формы](../../../docs/guides/hydration-rules.md#формы-присутствие-и-defaults) |
| 028 A: дочерние DTO, рекурсивные списки, each, варианты, scoped casts/providers и границы default() | [Discriminator](../../../docs/guides/hydration-rules.md#discriminator), [scope](../../../docs/guides/hydration-rules.md#scoped-cast-и-provider) |
| 028 B: приёмник, совпадение имён, потреблённые пути, null и false, проекции и sourceKey после JSON | [Дополнительные поля](../../../docs/guides/hydration-rules.md#дополнительные-поля) |
| 028 B: исключение receiver из запросов, DX без набора, ручные/plain DTO, query, opaque/cast ограничения | [Исходящие запросы](../../../docs/guides/hydration-rules.md#receiver-в-исходящих-запросах), [сериализация](../../../docs/guides/serialization.md) |
| 028 C: sourcePath/DTO path, JSON Pointer, Expected/Boundary/Unavailable, safe log | [Диагностика](../../../docs/guides/hydration-rules.md#диагностика-и-входы), [ошибки](../../../docs/guides/errors.md) |
| 028: Returns sync/promise, пагинация, composite, await; сырой HTTP cache; bypass handlers; глубина и циклы | [Входы и ограничения](../../../docs/guides/hydration-rules.md#диагностика-и-входы), [pipeline](../../../docs/technical/pipeline.md) |
| Переход пользователя и выбор профиля/набора | [Миграция](../../../docs/guides/migration.md), [методология SDK](../../../docs/guides/provider-methodology.md), [changelog](../../../CHANEGLOG.md) |
| Поиск новых классов и контрактов | [DTO](../../../docs/glossary/dto.md), [результаты](../../../docs/glossary/results.md), [указатель](../../../docs/glossary/README.md), [README](../../../README.md) |

## Проверки

Команды из корня репозитория:

```bash
composer check-docs
python3 .workflow/completed/pln-028-declarative-dto/artifacts/check-documentation-followup.py
php tests/Support/standalone-hydration-rules-smoke.php
php .workflow/completed/pln-030-continuation-errors/artifacts/implementation-doc-examples.php
php -l tests/Support/standalone-hydration-rules-smoke.php
git diff --check
```

Результат: 90 публичных документов и этот отчёт, 863 локальные ссылки и якоря без
ошибок. В исполняемом примере правил прошли 8 наблюдений, включая коллизию имён;
в примерах continuation — 7 проверок. PHP lint и whitespace чистые.

Результаты повторного запуска, ссылки и SHA-256 изменённых документов сохранены
в [documentation-followup-state.json](artifacts/documentation-followup-state.json).
Выводы: [локальные ссылки и якоря](artifacts/documentation-followup-links.json),
[пример правил и коллизия](artifacts/documentation-followup-rules.json),
[примеры resolver/token](artifacts/documentation-followup-await.json).

Это проверка документации и её исполняемых примеров. Полный набор тестов пакета
и замеры производительности не повторялись: runtime-код не менялся. Исходные результаты
приёмки и манифесты 028/030/031 относятся к своим commit и не перезаписывались.
