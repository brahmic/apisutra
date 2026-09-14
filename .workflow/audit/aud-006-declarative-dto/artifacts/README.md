# Воспроизведение аудита DTO

Из корня репозитория с установленными зависимостями:

```bash
php .workflow/audit/aud-006-declarative-dto/artifacts/probe.php
vendor/bin/pest tests/Unit/Serialization/HydratorTest.php tests/Unit/Serialization/HydrationCompatibilityTest.php tests/Unit/Serialization/HydrationErrorContractTest.php tests/Unit/Serialization/NestedDiscriminatorModeTest.php tests/Unit/Serialization/StrictUnwrapBigIntegerTest.php --compact
```

- [baseline.json](baseline.json) — версия, commit, сравнение с релизом, команды и SHA-256.
- [probe.php](probe.php) — 83 наблюдения через публичный API.
- [probe-results.json](probe-results.json) — сохранённый исходный результат, 0 расхождений.
- [targeted-tests.log](targeted-tests.log) — 135 passed, 644 assertions.
- [Stubs](Stubs) — изолированные именованные фикстуры, один тип на файл.

Probe самостоятельно добавляет namespace фикстур в Composer loader только текущего
процесса, не меняя composer.json. Проверка блокирует попытки автозагрузки Illuminate;
реальное HTTP заменено MockTransport. Standalone-вызовы не создают PipelineContext.
Все данные искусственные. JSON показывает `expected` текущего поведения, `actual`
и совпадение `matches`. Успешный запуск означает воспроизведение исходного состояния,
включая отсутствующие возможности, а не прохождение будущей приёмки AS-1–AS-4.

После изменения ядра часть ожиданий должна перестать совпадать. Не переписывать
сохранённый baseline как будто это исходное состояние новой версии; новые результаты
фиксировать отдельно с commit и командой. При необходимости повторной записи вывода
использовать отдельный файл, чтобы сохранить историческое доказательство аудита.

Примеры доступных расширений: [StrictProfile](Stubs/StrictProfile.php),
[StrictScalarCast](Stubs/StrictScalarCast.php), [StrictListCast](Stubs/StrictListCast.php),
[RejectStateDefault](Stubs/RejectStateDefault.php). Их ограничения описаны в
[основном аудите](../aud-006-readme.md); они не являются реализацией будущего API.

## Повторная проверка оригинала

```bash
php .workflow/audit/aud-006-declarative-dto/artifacts/colleague-probe.php
php .workflow/audit/aud-006-declarative-dto/artifacts/extensions-probe.php
vendor/bin/pest tests/Unit/Serialization/UriPipelineTest.php tests/Unit/RateLimit/JointQuotaTest.php tests/Unit/OperationInventory/OperationInventoryBuilderTest.php --compact
```

- [colleague-probe.php](colleague-probe.php) — адаптированный запуск исходного
  [скрипта коллеги](../../../issue/iss-002-apisutra-reuse/artifacts/probe.php).
  Восемь fixture-файлов читаются непосредственно из переданного комплекта, без копий.
  Изменены autoload, клиент и PSR-18 стенд; проверяемые условия P01–P19/P21 сохранены.
  P20 исключён с явной отметкой: классы внешнего SDK не предоставлены.
- [colleague-results.json](colleague-results.json) — 20 воспроизведённых наблюдений.
  [RecordingHttpClient](Stubs/RecordingHttpClient.php) возвращает локальные PSR-ответы
  через настоящий HttpTransport. В отличие от MockTransport, сохраняется PSR-18 граница;
  таймаутный интерфейс обслуживает мгновенный стенд, поведение реальных таймаутов здесь
  не проверяется. Версия ядра вынесена в recheck-baseline: Composer InstalledVersions
  для корневого пакета нельзя выдавать за закреплённую зависимость внешнего SDK.
- [extensions-probe.php](extensions-probe.php) и
  [extensions-results.json](extensions-results.json) — 14 наблюдений сочетания
  provider + Nested, raw-фабрики и двумерных списков. 0 расхождений.
  Этот скрипт блокирует Illuminate, HTTP-сценарии используют native fake.
- [recheck-tests.log](recheck-tests.log) — 85 passed, 373 assertions.
- [recheck-baseline.json](recheck-baseline.json) — состояние кода, команды, SHA-256
  входящего комплекта и новых артефактов. Исходный baseline сохранён, включая SHA
  удалённого пользователем `issue/002.md`; текущий источник — upstream-requirements.md.

Вердикты A01–A08 и уточнение прежнего F6 — в [повторной проверке](../recheck.md).
Успешные probes по-прежнему фиксируют фактическое поведение, в том числе оставшуюся
потерю имени поля при отказе provider; это не приёмка будущих правил AS-1–AS-4.

## Проверка рецензии аудита

```bash
php .workflow/audit/aud-006-declarative-dto/artifacts/peer-review-probe.php
python3 .workflow/audit/aud-006-declarative-dto/artifacts/verify-review.py
```

- [peer-review-probe.php](peer-review-probe.php) и
  [peer-review-results.json](peer-review-results.json) — 35 наблюдений C01–C35:
  одиночный Nested, scalar-списки, provider path/state, nullable properties,
  global/extension casts и response handlers. Ожидания заданы вручную.
  Реальный HttpTransport получает локальные PSR-ответы; Illuminate блокируется.
- [review-integrity.json](review-integrity.json) — проверка прежнего комплекта
  до редакторских правок: 68 совпавших SHA, 1 удалённый источник, 49 php -l.
- [review-probe-results.json](review-probe-results.json),
  [review-colleague-results.json](review-colleague-results.json),
  [review-extensions-results.json](review-extensions-results.json) — повторные
  83/20/14 наблюдений, побайтно равные прежним результатам.
- [review-targeted-tests.log](review-targeted-tests.log) и
  [review-recheck-tests.log](review-recheck-tests.log) — повторно 135/644 и 85/373.
- [verify-review.py](verify-review.py) и
  [review-verification.json](review-verification.json) — сравнение 20 verify-условий
  с оригиналом, повторных JSON и старых SHA с учётом двух документированных
  исправлений входящей навигации. P20 явно исключён.
- [editorial-changes.json](editorial-changes.json) — SHA до/после для исправленного
  ID исходного отчёта и его Markdown-ссылок. Содержательные утверждения и PHP
  исходного комплекта сохранены; старые baseline не переписаны.
- [peer-review-baseline.json](peer-review-baseline.json) — новый набор SHA и команд.

Итоги и проверка каждого замечания — в [peer-review](../peer-review.md).
Скрипты коллеги из scratchpad в комплект не входили; C01–C35 созданы независимо
и находятся в artifacts, а не только во временном каталоге.
