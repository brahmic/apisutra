# Итоговая сверка согласованного объёма — 2026-09-12

Основание: исходные AS-01–AS-14 issue/001, F01–F16 аудита 001, согласованные
планы 002–020. Исходные предложения не равны принятому объёму: ниже явно указаны
сохранённые ограничения и отложенные расширения. Прежние audit probes описывают
исторический baseline; доказательства текущего поведения — регрессионные тесты.
Пути в таблице — относительно корня репозитория.

| Требование | Реализация и доказательство | Остаток / граница |
| --- | --- | --- |
| AS-01 | 006: runtime → attribute → config, TransportOptions, timeout/connect timeout, ms/deadline. tests/Integration/TransportTimeoutIntegrationTest.php проверяет реальное прерывание и независимые лимиты. | Неизвестный транспорт с активным лимитом явно отклоняется; произвольный PHP callback нельзя принудительно остановить. |
| AS-02 | 004/017/019: нормализация до retry, actual HTTP response, decoding/hydration/hook/локальные ошибки и nullable response. tests/Unit/Core/BatchFailureContractTest.php, tests/Unit/Auth/AuthRecoveryContractTest.php. | AuthRefreshFailedException хранит основной 401 и результат зависимости отдельно. Не подменяет их одной сетевой ошибкой. |
| AS-03 | 005/006/017: safeMethods + Retry(safe), отключённые по умолчанию обычные retry, воспроизводимость тела, attempts, Retry-After/backoff, общий бюджет, успешный refresh как условие auth retry. tests/Unit/Retry/, tests/Unit/Auth/. | Неблокирующие async wait отложены с 001. Универсальный новый policy interface не вводился; используются согласованные config/attribute/существующие extension points. |
| AS-04 | 002/003: автоматическая identity/tenant, безопасный digest, scopes, TTL без продления, scoped clear. tests/Unit/Cache/. | Custom key намеренно объединяет разные варианты внутри identity/tenant; файл не кешируется. При custom auth без стабильной identity — локальное пространство. |
| AS-05 | Откорректирована документация Promise/batch/transport; текущая sync/async доставка ошибок покрыта 017/019. | Настоящий async-first, неблокирующий I/O и ожидания, cancellation и scheduler отложены в pln-001. AS-05 целиком реализованным не считается. |
| AS-06 | 007/008: компонентная сборка URI, query order/duplicates, false/0/null, кодирование path, замена собственной auth пары. tests/Unit/Serialization/ и tests/Integration/ExternalUrlTransportTest.php. | В 020 дополнительно сохранён exact request target при нормализации libcurl 8.22. Полный URL не пересобирается как query map. |
| AS-07 | 004/012/013: строгий JSON, ошибка unwrap, большие integers строкой, строгие поля/JsonCast. tests/Unit/Serialization/, standalone-json-smoke.php. | Нет legacy fallback повреждённого JSON в []; это согласованная несовместимость. |
| AS-08 | 008/009/010: file streams, borrowed handles, safe publish, replay checks, external URL isolation, capability errors. tests/Unit/Files/, tests/Integration/ExternalUrlTransportTest.php, standalone-streaming-smoke.php. | Файловый HTTP-кеш отклонён пользователем. Recorder не читает поток; playback требует отдельной файловой fixture. |
| AS-09 | 010/011/014/017/019: body transitions, auth runtime scope, isolated token store/locks, client validation factory. Nullable runtime inventory и соответствующие тесты зафиксированы в завершённом 019. | Обязательных новых scope/prefix настроек нет; caller-owned streams не закрываются. |
| AS-10 | 002/003/019: safe export, redaction, 64 KiB default, body omission metadata, точные большие числа, запись целой fixture без overwrite, явная recording_failed. tests/Unit/Diagnostics/RecordingContractTest.php, standalone-contracts-smoke.php. | Raw доступ явный. Корректные replay fixtures не ограничены размером; invalid UTF-8 отклоняется. После аварийного завершения может остаться скрытый temp; crash cleanup не обещан. |
| AS-11 | 016: portable keys, перепроверка окна после wait, общий бюджет, store errors и локальный отказ без искусственного HTTP. tests/Unit/RateLimit/, standalone-rate-limit-smoke.php. | Строгая распределённая атомарность/расширенная координация отложена в 015; очередь запросов — ответственность приложения. |
| AS-12 | 018: discovery, explicit RequestFactory, сохранение пользовательских bindings/request values, HTTP/Artisan/sequential jobs/config:cache. tests/Integration/Laravel/verify.php. | Проверен Laravel 12. Отдельный queue worker, Octane и Laravel 13 не заявлены как проверенные. |
| AS-13 | 012/013/019: read-only DTO, From, вложенные DTO/discriminator, unwrap, null/false/0/missing, 5 HTTP методов, пользовательский meta resolver, cursor 0, guards, runtime toggles. tests/Integration/CoreFlowIntegrationTest.php, tests/Unit/Pagination/. | Повтор visited cursor прекращает обход, cursor Partial после ошибки не продолжает потенциально неверную цепочку. |
| AS-14 | 020: PHP 8.4/8.5, dev quality gates, production-only archives, dependency resolution, migration guide, composer metadata и документированный baseline. | Удалённый CI на итоговом commit, tag и публикация требуют следующего шага. Наличие YAML не считается результатом CI. |

## Находки первоначального аудита

| Находка | Итог |
| --- | --- |
| F01 — identity кеша | Исправлена в 002/003; автоматическое разделение, custom key сохранён внутри пространства. |
| F02 — секреты в URL диагностики | Исправлена в 002/008/019; безопасный экспорт маскирует signed URL и известные поля. |
| F03 — игнорирование timeout | Исправлена в 006; проверен штатный реальный HTTP и явный отказ неизвестной capability. |
| F04 — обещание nonblocking async | Обещание исправлено в документации; сама реализация отложена в 001. |
| F05 — auth lock release | Исправлена в 011; 017 добавляет реальные recovery outcomes. |
| F06 — ключи, запрещённые PSR-16 | Исправлена в 002/003/011/016. |
| F07 — глобальный validation context | Исправлена в 014; фабрика привязана к выполняющему клиенту. |
| F08 — ConnectException до retry | Исправлена в 004/005; сеть нормализуется до решения о повторе. |
| F09 — overflow при гидратации | Исправлена в 013; явная hydration_error без раскрытия значения в автоматическом логе. |
| F10 — неверный JSON считается успехом | Исправлена в 004. |
| F11 — hydration как connection_failed | Исправлена в 004/019, включая throwOnErrors + parallel batch/pool. |
| F12 — sliding TTL | Исправлена в 002; hit не обновляет время жизни. |
| F13 — Laravel quickstart/DI | Исправлена в 018; explicit factory миграция описана и проверена реальным приложением. |
| F14 — состав дистрибутива | Исправлена в 020 для новых архивов; старые опубликованные версии не меняются. |
| F15 — прямые ожидания | Исправлена в 006/016 для текущего синхронного контракта; неблокирующий scheduler отложен. |
| F16 — duration units | Документация и PHPDoc исправлены в 019; значения остаются ms. |

Проверенные остатки AS-02 (batch/pool), AS-09 (runtime) и AS-10 (diagnostics)
закрыты в согласованном объёме. Отдельный точный PHPStan baseline отражает прежний
долг по типам; 99 записей разобраны в phpstan-review.json и отложены в 021.
Он не означает ни 99 воспроизведённых дефектов, ни отсутствие долга по типам.
