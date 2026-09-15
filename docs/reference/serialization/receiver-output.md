# Receiver в исходящих запросах

## Receiver в исходящих запросах

Клиент с набором исключает receiver по классу модели: и у гидратированного, и у вручную
созданного объекта. Он не отправляется под своим именем и не разворачивается в корень.
`toArray()` и DtoSerializer без набора продолжают сериализовать `_extra` как обычное свойство.

В body, BodyRoot, multipart-body и при сборке query правило действует на DtoInterface,
plain DTO, списки и публичные plain-обёртки любой допустимой глубины. Для plain DTO
сохраняется JSON остальных публичных свойств; при отсутствии свойств получается `{}`.
Ядро заменяет только контейнеры на пути к receiver, не изменяет DTO и не вызывает
конструкторы. Header/path/file по-прежнему DTO не сериализуют.

Query URL builder допускает только скаляры и плоские списки: исключение receiver
не делает DTO допустимым query-значением. Структура отклоняется до HTTP. Для такого
API опишите явные query-поля отдельной моделью запроса.

Непрозрачные преобразования имеют явные ограничения:

- Класс с receiver и DateTimeInterface, JsonSerializable, Stringable или `toArray()` без DtoInterface
  отклоняется при компиляции набора.
- Property Cast и cast по типу, получающий значение с видимым receiver, даёт
  `SerializationException` **до вызова cast и HTTP** (`serialization_error`).
  Это относится и к JsonCast на BodyRoot/Body/query.
- Обход не раскрывает JsonSerializable, Stringable, пользовательский `toArray()`,
  DateTime, enum и closure. Если такая внешняя обёртка прячет DTO с receiver,
  её представление остаётся ответственностью SDK.
- To, DateTimeTo, Query, Body, BodyRoot, Header, Path, File на receiver —
  `ConfigurationException` при компиляции.

Prepared request, requestDebug, ключ HTTP cache и лог используют представление уже без
receiver. Чтобы отправить дополнительные данные, создайте явную модель запроса.

[Входной остаток и коллизии имён](../dto/extras.md) · [DX/wire](dto-output.md).
