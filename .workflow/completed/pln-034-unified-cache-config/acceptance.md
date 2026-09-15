# Приёмка перехода

| ID | Проверка |
| --- | --- |
| U01 | Пустой/несвязанный ClientConfig::with сохраняет блок со всеми ссылками; null и блок без store тоже сохраняются |
| U02 | Полная замена применяет новый блок точно; блок без store отключает прежний backend |
| U03 | Частичная копия CacheConfig меняет TTL/store/mode и сохраняет остальные поля; исходник неизменён |
| U04 | null отдельно для блока и store/identity/locks; неверные null/типы и неизвестные имена дают Error/TypeError |
| U05 | Named, array unpacking, Laravel, readonly и новый позиционный контракт; старые cache/cacheStore отвергаются |
| U06 | Копирование не вызывает store/identity/locks и не очищает записи, intentional shared objects сохраняются |
| U07 | Два GET у исходного и скопированного клиентов используют запись; новый TTL действует только на новые записи |
| U08 | HTTP Disabled сохраняет auth store; общие токены переиспользуются после изменения timeout/режима |
| U09 | Отключённый блок/store не читается даже с runtime Enabled и clearCache; исходный клиент продолжает работать |
| U10 | Полная замена store/namespace/identity изолирует новые записи; invalidation сохраняет границы |
| U11 | Явный auth locks сохраняется при store:null и снимается при cacheConfig:null; fallback locks из store прежний |
| U12 | HTTP и token записи fixture до разделения store читаются без изменения ключей и без refresh |
| U13 | Справочник, исполняемый client showcase, API registry, migration и оба архива согласованы |
| U14 | Полные тесты, lint, analyse, check-docs/analyse-docs и CI проходят; GitHub prerelease и Packagist указывают на проверенный commit |
