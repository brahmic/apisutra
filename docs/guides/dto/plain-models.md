# DTO без атрибутов

Чтобы использовать модели без атрибутов ApiSutra, соберите неизменяемый `HydrationRules`
и передайте его в `ClientConfig::hydrationRules`. Один набор описывает mapping, вложенные
DTO, строгие типы, присутствие полей и сохранение дополнительных данных. Для обработки
без клиента используйте `Hydrator::forRules($rules)`: Laravel, контейнер и HTTP не нужны.

## Сквозной пример

[Опубликованный пример](../../example/hydration-rules/README.md) состоит из отдельных
файлов DTO, набора правил и scoped cast. Запуск из checkout после Composer install:

```bash
php docs/example/hydration-rules/run.php
```

В установленном пакете путь начинается с `vendor/brahmic/apisutra/`.
[run.php](../../example/hydration-rules/run.php) строит один набор для standalone
и `ClientConfig`. Он описывает owner, список `rows` с проекцией `each: 'value'`,
строгий список ids, запрет явного null для count и receiver `_extra`.

Ожидается: owner.id = 7, owner._extra = `['future' => false]`, items[0].id = 8,
count = null. В корневом `_extra` сохраняются `next_feature: null` и соседние meta
элементов rows. [Форма остатка](../../reference/dto/extras.md) описана отдельно.

Передайте `$config` своему SDK-клиенту. `#[Returns(ReportDto::class)]` использует
тот же набор. Необязательный последний параметр `rules` есть также у конструкторов
`Hydrator`, `DtoSerializer` и `Serializer`. Клиент передаёт набор обоим направлениям.
`$config->with(hydrationRules: null)` создаёт конфигурацию без набора;
`with()` без override сохраняет исходный набор.

`Hydrator::default()` и `DTO::from()` набор клиента не наследуют. Для одинакового
поведения standalone и клиента передавайте один набор явно. В Laravel собирайте
набор в provider/factory, а не сохраняйте живые descriptors в кешируемом config.

## Переход существующего SDK

Рабочие атрибутные рецепты остаются доступны: [provider для Present/Null](../../reference/dto/defaults.md#provider-для-найденного-значения)
проверяет запрет null и форму до Nested; `Nested(itemCast:)` может проверить scalar-элемент
или вернуть raw-объект через фабрику. Такой itemCast создаётся без аргументов, поэтому
параметризованный строгий scalar cast требует отдельного класса. Bare array и PHPDoc
не дают проверки элементов. `list(list(dto(...)))` заменяет RowCast для двумерного списка
без отдельного DTO ряда. Raw-фабрика, напрямую создающая объект, гидратор не вызывает.

При переносе на набор удалите конфликтующие входные атрибуты только у полей с FieldRule.
Рекурсивные вызовы Hydrator внутри casts/providers переводите на scope; простой вызов
raw-фабрики менять не требуется. Не включайте strict до проверки реальных типов JSON.
Исходящую модель с receiver пересмотрите отдельно: `_extra` больше не отправляется
клиентом с набором, а cast всего объекта с receiver запрещён. Без набора эти изменения
не применяются. Общая [миграция версии](../../migration/README.md) и исправления
[continuation](../../reference/execution/continuation-await.md#миграция-с-эвристического-ожидания) описаны отдельно.

Для моделей, где конструктор сам задаёт `type` или фиксированные массивы, используйте
[constructorValue](../../reference/dto/constructor-values.md): вход проверяется после
штатных преобразований, readonly-свойство не записывается повторно.
