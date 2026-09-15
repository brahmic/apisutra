# Целевая структура документации

Проект раскладки для [плана 027](pln-027-readme.md), база `cddd036`.
Имена в дереве — будущие пути, а не ссылки на уже созданные страницы. Старые страницы
перехода здесь не показаны; их адреса сохраняются по [карте](content-map.md).
Аудитории и решение о входе для ИИ — в [отдельном описании](audiences.md).

## Дерево

```text
README.md
CHANEGLOG.md
CONTRIBUTING.md
development/
  README.md
  architecture.md
  pipeline.md
  execution.md
  error-handling.md
  caching-retry.md
  attributes.md
  testing.md
  documentation.md
docs/
  README.md
  start/
    README.md
    create-sdk.md
    agent.md
    add-operation.md
    describe-dto.md
    use-sdk.md
    diagnose.md
  guides/
    README.md
    quickstart.md
    requests.md
    troubleshooting.md
    sdk/
      README.md
      analysis.md
      design.md
      first-operation.md
      coverage.md
      release.md
    dto/
      README.md
      plain-models.md
      attribute-models.md
    integration/
      standalone.md
      laravel.md
      multi-service.md
    testing/
      unit.md
      live.md
    recipes/
      pagination.md
      continuation.md
      files.md
      extensions.md
  reference/
    README.md
    client/
      README.md
      configuration.md
      construction.md
      discovery.md
      resources.md
      versioning.md
      validation.md
      catalogs.md
      operation-inventory.md
    request/
      README.md
      declaration.md
      composition.md
    dto/
      README.md
      models.md
      profiles.md
      field-rules.md
      scalars.md
      defaults.md
      shapes.md
      variants.md
      extras.md
      scope.md
      diagnostics.md
      collections.md
      lifecycle.md
    serialization/
      README.md
      request-parts.md
      uri-query.md
      body.md
      dto-output.md
      casts.md
      receiver-output.md
    auth/
      README.md
      strategies.md
      tokens.md
      credentials.md
    execution/
      README.md
      transport.md
      retry.md
      rate-limit.md
      cache.md
      deadlines.md
      pagination.md
      batch-pool.md
      continuation-state.md
      continuation-await.md
    results/
      README.md
      handles.md
      errors.md
      observability.md
    attributes/
      README.md
      http.md
      request.md
      hydration.md
      response.md
      behavior.md
      hooks.md
    files/
      README.md
      uploads.md
      downloads.md
      archives.md
    extensions/
      README.md
      hooks.md
      extensions.md
    testing/
      README.md
      mocking.md
      fixtures.md
      live.md
    integrations/
      README.md
      laravel.md
      redis.md
  glossary/
    README.md
    architecture.md
    attributes.md
    auth.md
    client.md
    collections.md
    dto.md
    execution.md
    extensions.md
    files.md
    laravel.md
    pagination.md
    pipeline.md
    requests.md
    results.md
    testing.md
  migration/
    README.md
    v0.2.0-alpha.1.md
    unreleased.md
  example/
    README.md
    sdk/
      README.md
```

## Назначение папок

`docs/` адресован пользователю пакета. `CONTRIBUTING.md` и `development/` —
разработчику ApiSutra, доступны в checkout и исключаются из dist. Ниже start/guides/
reference/glossary/migration/example указаны относительно docs, development — от корня.

| Раздел | Владеет | Не хранит |
| --- | --- | --- |
| `start/` | Задачи пользователя; общий маршрут и необязательный вход для агента | Разработку ядра, отдельные правила поведения для ИИ |
| `guides/` | Последовательность действий и рабочие примеры | Вторую версию defaults и приоритетов |
| `reference/` | Полные декларации и поведенческие контракты по теме | Пошаговое создание целого SDK |
| Корневой `development/` | Внутреннее устройство ApiSutra, тесты и сопровождение пакета | Копию публичного контракта или обязательные знания пользователя |
| `glossary/` | Термин в 1–3 предложениях и ссылка на владельца | Каталог всех методов/параметров класса |
| `migration/` | Что изменилось между версиями и действия пользователя | Полную копию текущего контракта |
| `example/` | Исполняемый образец и искусственные данные | Альтернативную спецификацию пакета |

`docs/README.md` и индексы групп ведут по задачам; они не перечисляют все заголовки
всех страниц. Сохраняем `example/` в единственном числе и тематические файлы
`glossary/`: косметический перенос сам по себе не улучшает поиск.
Обычная глубина — `docs/<вид>/<тема>/<файл>.md`; дополнительные тематические уровни
в этой раскладке не нужны. Дерево исходников учебного SDK — отдельный случай.

## Владельцы контрактов

- `reference/client/configuration.md` — каталог **всех** полей ClientConfig:
  имя, тип, ссылка на тематическую спецификацию. Не повторяет её defaults.
  Базовые параметры сборки и `with()` описывает `client/construction.md`.
- `reference/attributes/*` — target, сигнатура, параметры, defaults атрибута
  и ссылка на его поведение. Например, параметры `Nested` — в `hydration.md`,
  формы и обработка данных — в `dto/shapes.md` и `dto/variants.md`.
- `dto/field-rules.md` — builders HydrationRules/DtoRules/FieldRule, регистрация
  класса, конфликты атрибутов и компиляция. `dto/profiles.md` — выбор policy,
  inheritance, приоритеты профиля/набора; алгоритм не дублируется в builders.
- `dto/scalars.md`, `defaults.md`, `shapes.md`, `variants.md` — соответственно
  strict/Legacy, состояния значения, формы/списки, discriminator. Общий порядок
  поля описывается в `field-rules.md`, подробные правила — у этих владельцев.
- `dto/extras.md` — потребление источника и остаток `_extra`;
  `serialization/receiver-output.md` — исходящее исключение receiver и ограничения.
  Входящий и исходящий контракт связаны ссылками, но не повторяют друг друга.
- `dto/lifecycle.md` — constructor-first, object defaults, аргументы атрибутов,
  кеш и намеренно общие объекты (031); `dto/scope.md` — область вызова (028).
- `serialization/dto-output.md` — DX `toArray()` и policy DTO;
  `body.md` — wire body; `request-parts.md` — распределение полей и преобразования;
  `uri-query.md` — URI и query encoding. В каждом описаны собственные приоритеты.
- `execution/continuation-state.md` — resolver/Context/State и Ready/Pending/Failed;
  `continuation-await.md` — входы ожидания, режимы, token, attempts, Outcome и ошибки.
  `results/errors.md` ссылается на await-контракт, не повторяя его.
- `results/handles.md` — API результата; `results/errors.md` — классификация/маппинг;
  `dto/diagnostics.md` — path/sourcePath; `results/observability.md` — debug/log
  и безопасное представление. Граница точного и маскированного контекста общая.
- `execution/cache.md` — HTTP cache; кеш метаданных ссылается на `dto/lifecycle.md`.
  Token cache и refresh принадлежат `auth/tokens.md`.
- Транспорт, retry, квоты, deadline и файлы имеют собственные страницы; одна
  универсальная таблица приоритетов для всех механизмов не вводится.

У каждого контракта в `inventory.md` один владелец вплоть до раздела.
Допустимы короткое напоминание и пример значения; повтор полной таблицы или
алгоритма означает, что владение выбрано неверно. В `.agents/` остаются правила
сопровождения и ссылки, публичные требования к SDK принадлежат `guides/sdk/`.

## Размер и критерии разделения

Считаются все строки Markdown, включая таблицы и код; дополнительно отчёт показывает
размер UTF-8, чтобы длинная строка таблицы не скрывала большой документ.

| Вид | Ориентир, строк | Предел без обоснованного исключения |
| --- | --- | --- |
| Индекс / README | 40–100 | 150 |
| Точка входа | 80–150 | 200 |
| Практический guide | 100–220 | 300 |
| Тематический reference | 100–240 | 320 |
| Терминологический файл | 40–140 | 180 |
| Технический обзор | 60–180 | 220 |

Это верхние границы, а не требование дописывать короткую полезную страницу.
Размер свыше 24 KiB также требует редакционной проверки, независимо от числа строк.
Исключение фиксируется с причиной в `size-exceptions.md` плана; нельзя просто
поднять общий порог ради одного перегруженного файла. Changelog и исторические
заметки выпусков исключены из лимита: их нельзя произвольно дробить или переписывать.

Разделять по отдельному вопросу читателя, собственному контракту или условиям чтения.
Не делать `part-1.md`, `part-2.md`, отдельный файл на каждый setter либо каталог
однотипных страниц из двух абзацев. После устранения дублей небольшой раздел можно
объединить с ближайшим владельцем: изменение дерева и карты фиксируется вместе.
Ни один файл из дерева не создаётся пустым ради соответствия схеме.

Большие примеры выносятся в PHP-файлы `example/sdk/`, а не растягивают guide.
Параметры одного атомарного атрибута не разрываются между страницами только ради лимита.

## Совместимость и навигация

Навигация пользователя не включает разработку ядра. Полная ссылка на CONTRIBUTING
в репозитории даётся отдельно. Старые technical-адреса переходят к публичному
reference или к URL development в репозитории; относительная ссылка на исключённый
из dist файл недопустима. Правила поставки — в [границах аудиторий](audiences.md).

При переносе старый адрес получает короткую страницу перехода: зачем он перенесён,
прямые ссылки и старые якоря, нужные входящим ссылкам. Старые полные правила удаляются.
Ориентир объёма перехода — до 40 строк плюс две на сохраняемый якорь.
В `moves.md` записываются и заголовочные якоря, и явные HTML id, включая кириллицу
и повторные заголовки. Совместимость хранится в текущем цикле выпуска; удаление
переходов требует отдельного решения о совместимости документации.

Внутренние активные ссылки обновляются. Исторические доказательства и скрипты
приёмки не переадресовываются массово; при необходимости поддерживается прежний
адрес. Текущие smoke, извлекающие код из guide, переводятся на новый источник
вместе с переносом. В новую приёмку не включаются старые скрипты как будто они
проверяют новые файлы. Безымянные ссылки только на общий индекс заменяются прямыми.
