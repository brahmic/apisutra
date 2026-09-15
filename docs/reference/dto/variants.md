# Варианты элементов списка

## Discriminator

`ValueShape::variants(string $discriminator, array $map, NestedDiscriminatorMode $mode = Value,
NestedUnknownVariant $unknown = KeepRaw)` применяется только как элемент `list()`.
Enums расположены в `Brahmic\ApiSutra\Enums\DataTransfer`.

Value выбирает класс по значению пути discriminator; Key — по первому ключу обёртки
(пустой discriminator означает текущий объект). Map содержит значения/ключи и классы DTO.
Неизвестный или отсутствующий вариант обрабатывается через KeepRaw, Skip или Error.
Error даёт `unknown_nested_variant`. Ошибка известного варианта никогда не подавляется.
KeepRaw несовместим с typed collection, принимающей только DTO: это ошибка конфигурации.
