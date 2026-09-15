# Контекст вложенной гидратации

## Scoped cast и provider

`HandlerSpec($class, $args)` создаёт обработчик на каждое применение поля или элемента.
Property Cast создаётся на поле, атрибутный Nested.itemCast — один раз на проход списка.
Профили и явно переданные экземпляры сохраняют прежний жизненный цикл.

Для вложенной гидратации с тем же набором реализуйте `ScopedCastInterface`
или `ScopedDefaultValueProviderInterface` из Contracts\Interfaces\Casting и
Contracts\Interfaces\DataTransfer соответственно. Полный
[EntryCast](../../example/hydration-rules/src/EntryCast.php) вызывает
`$scope->hydrate($value, EntryDto::class)`. Он поставляется вместе с DTO и выполняется
[примером](../../example/hydration-rules/README.md).

Scope передаётся при **любой** регистрации обработчика: descriptor, атрибут, itemCast,
provider или профиль. Scoped provider реализует
`resolveInScope(mixed $value, ValueState $state, array $source, HydrationScope $scope): mixed`
и прежний `resolve(...)`. `scope->hydrateCollection($items, $class)` сохраняет набор
в помощниках вроде RowCast; `scope->context()` возвращает PipelineContext или null standalone.
Не храните scope в singleton или свойстве переиспользуемого обработчика. Вызов
`Hydrator::default()` внутри помощника — явная граница: набор туда не передаётся.
