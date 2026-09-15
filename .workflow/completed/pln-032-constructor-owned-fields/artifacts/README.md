# Доказательства готовности 032

[Legacy probe](legacy-typing-probe.php) сравнивает обычное поле Hydrator,
ReflectionProperty::setValue, ScalarValues::coerce и Hydrator с noTransform.
Семь сценариев, ожидания заданы вручную; порядок объявления двух union проверяется
в обоих вариантах. Это проверка действующего кода, не реализация constructorValue.

Из корня репозитория:

```bash
php .workflow/completed/pln-032-constructor-owned-fields/artifacts/legacy-typing-probe.php
```

Для стандартного пути float|string ← 5 даёт "5", bool|int ← 'false' — false.
С noTransform значения того же Hydrator равны 5.0 и true. Эталон нового режима —
обычное записываемое поле **с теми же правилами**, а не один из низкоуровневых helpers
во всех случаях. Эту границу проверяет расширенный D10.

Результат probe, команды/логи 66 адресных тестов, сравнение 19 наблюдений issue,
среда и проверки документов сохранены единственным комплектом в
[общих доказательствах 032/033](../../../current/pln-033-cache-config-copy/artifacts/README.md).
Скрипт запуска, правила повторения без перезаписи и контрольные суммы находятся там.
Историческая проверка готовности предшествует реализации.

[Baseline принятого commit](implementation-baseline/report.json),
[команды реализации](implementation/commands.json) и [итоговая матрица](../implementation.md)
содержат последующие доказательства. SHA снимков проверяются на указанном commit;
пути внутри снимков сохранены в исходном виде до переноса плана.
