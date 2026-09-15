# Воспроизведение аудита ApiSutra 0.3

Сохранённый запуск: PHP 8.5.4, ApiSutra v0.3.0-alpha.1,
reference `6a649f5e247adba3ecc0c82f1ba68cb74c018aba`, 2026-09-15.
Фактическая версия зависимости выводится каждым скриптом.

## Запуск в репозитории

После обычной установки зависимостей, из корня проекта:

```bash
php .workflow/audit/aud-002-apisutra-contracts/artifacts/protocol-probe.php --verify
php .workflow/audit/aud-002-apisutra-contracts/artifacts/upstream-probe.php --verify
```

Скрипты запускаются отдельными процессами. Имена вспомогательных функций и классов
локальны для каждого запуска; включать оба скрипта в один PHP-процесс не нужно.
Fixtures расположены по одному именованному типу в файле и подключаются явно,
без изменения Composer autoload и постоянного набора тестов пакета.

Без `--verify` выводятся факты текущего выполнения в JSON. С этим флагом скрипт
дополнительно сравнивает наблюдения и reference с сохранённым снимком, завершаясь
кодом 1 при расхождении. Снимки не переписываются автоматически.

**Совпадение со снимком не означает выполнения требований AS-5/AS-6.**
В снимках присутствуют подтверждённые ошибки. После новой версии нужно разобрать
расхождения и проверить критерии требований, а не принять старое поведение за цель.

## Комплект для разработчика ApiSutra

Для агностичного воспроизведения достаточно upstream-probe.php, bootstrap.php,
upstream-observations.json и файлов `fixtures/`. Можно передать всю папку artifacts.
В upstream-скрипте используются только нейтральные классы AuditClient,
AuditHttpClient, Store, AppProvider, ProbeRequest, ConstructorOwned, OwnedEnvelope,
ReadOwned. Классы MAX и Laravel-приложение не загружаются.

Путь к другому Composer autoload можно передать аргументом:

```bash
php artifacts/upstream-probe.php /path/to/project/vendor/autoload.php
```

Нужны ApiSutra с её зависимостями и `guzzlehttp/psr7` для искусственных PSR-ответов.
Для protocol-probe.php нужны классы/fixtures этого репозитория и его dev-autoload;
он сравнивает реальные модели SDK с текущими декодерами, но ответы синтетические.

## Состав наблюдений

| Диапазон | Что проверяется |
| --- | --- |
| P01–P17 | BotInfo, Message без attachments, строгость, missing/null, extras, формы списков, Returns и пути |
| P18–P21 | Query/body, исключение extras из исходящего DTO, изоляция правил, вложенные списки/варианты |
| P22–P30 | Свойства конструктора, naming, агностичный пример, scoped cast и повреждённый известный вариант |
| P31–P39 | Переполнение int, fake, receivers, числовые ключи, успех/missing/null при unwrap |
| U01–U04 | Неизменяемость конфигурации, замена блоков, фактическое поведение fromLaravel |
| U05–U09 | Работа кеша, проигнорированная замена блока, null и проверенные обходы |
| U10–U12 | Число попыток для GET при retry=null, attempts=1 и attempts=2 |
| U13–U14 | Потеря отдельно переданного store при несвязанном override и копировании без overrides |
| U15–U19 | Свойство, заполненное конструктором: standalone, список, Returns, конфликт и missing |

Исходники: [protocol-probe.php](protocol-probe.php), [upstream-probe.php](upstream-probe.php).
Результаты: [39 наблюдений P](protocol-observations.json),
[19 наблюдений U](upstream-observations.json).

Все HTTP-ответы подставляются AuditHttpClient или RecordingHttpClient; даже сценарий
неактивного preventStrayRequests попадает только в искусственный транспорт.
Адрес example.invalid не используется для реальной сети. Токенов, Redis,
отправки сообщений и ожидания окна квоты нет.
