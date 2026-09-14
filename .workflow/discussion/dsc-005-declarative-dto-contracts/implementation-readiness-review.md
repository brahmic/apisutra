# Проверка готовности принятых контрактов 028, 030 и 031

- Дата создания: 2026-09-14
- Дата обновления: 2026-09-14

Последующая проверка 2026-09-14: IR-01–IR-05 закрыты в контрактах и приёмке;
сигнатура DefaultSpec::value согласована с enum-значениями. План 031 реализован:
[результаты](../../completed/pln-031-metadata-value-isolation/implementation.md).
Ниже сохранён вердикт предыдущего разбора до этих правок.

## Вердикт

Контракты, матрицы приёмки и ADR действительно появились. Решение В12 принято:
receiver исключается из запросов клиента с набором, включая вручную созданные DTO,
а сериализация без набора сохраняет обычное свойство. Повторного внешнего ответа
по этому продуктовому выбору не требуется.

Полную готовность к основному коду подтвердить пока нельзя: есть несовместимое
с текущим API ожидание в приёмке 031, незаданный публичный случай 030 и незаданные
границы исходящей сериализации в 028. Это точечные правки существующих контрактов,
не основание для нового плана или повторного общего аудита.

| План / порция | Что осталось |
| --- | --- |
| 031 | Исправить M12: Cast не исполняется в header/path; отделить M15 для включённого кеша. Основное решение об изоляции уже выбрано |
| 030 | Определить декларацию для нетипизированного await без атрибута; согласовать обёртку ошибки scalar Ready с C20 |
| 028 A | Явно ограничить содержимое DefaultSpec.value и добавить проверку вложенного объекта |
| 028 B | Определить взаимодействие исключения receiver с кастом целого DTO, toArray/jsonSerialize и строковыми представлениями |
| 028 C | Отдельного противоречия выбранному правилу sourcePath/безопасного лога не обнаружено; остаются зависимости от нужных частей A/B |

Код пакета, тесты, benchmark и проверяемые контракты не менялись. Добавлены этот
отчёт и результаты проверки. Воспроизведения ниже запускались через PHP stdin.

## Что подтверждено

База — `eeb0b6a6154f8f95799be08f1389b799b19a6c24`, PHP 8.4.15.

| Проверка | Результат |
| --- | --- |
| review / followup / clarification / metadata probes | 20/20, 10/10, 14/14, 33/33; все результаты побайтно совпали с сохранёнными |
| SHA-256 clarification-state / baseline-state | Совпали 12 и 34 файла |
| Манифесты первого разбора / 029 | Совпали 24 и 45 файлов |
| Serialization + continuation/composite | 433 passed, 1385 assertions |
| Локальные ссылки/якоря проверяемого комплекта | 20 документов, 284 ссылки, ошибок нет; это комплект до добавления данного отчёта |
| Whitespace проверяемых документов и git diff | Ошибок нет |
| cost-benchmark.php | Выполнены 27 сценариев, по 7 прогонов, OPcache CLI/JIT выключены |

[Результаты](artifacts/implementation-readiness-checks.json),
[тесты](artifacts/implementation-readiness-tests.log),
[новое измерение стоимости](artifacts/implementation-readiness-cost.json).
Измерение подтверждает работоспособность benchmark на исходной версии, но не
производительность будущего исправления. Старые evidence-файлы не переписаны.

## IR-01. 031: M12 требует ранее отсутствовавшего применения Cast

Приоритет P2, подтверждённое противоречие приёмки и действующего API.

[M12](../../completed/pln-031-metadata-value-isolation/acceptance.md#матрица) ожидает
счётчики [1, 1] для Cast в query, body, header и path. Однако
[RequestPartsCollector](../../../src/Serialization/RequestPartsCollector.php)
в ветках Header и Path выполняет только serializeEnumOnly и не вызывает Cast.
Публичный Serializer с исходным значением 0 даёт header="0" и путь /items/0
при обоих режимах кеша и обоих вызовах. До [1, 1] эти ветки не дойдут после одного
исправления изоляции объектов.

Исправление приёмки: оставить проверку [1, 1] для существующих cast-путей
query/body/вложенного wire DTO, а для header/path закрепить прежнее отсутствие
вызова Cast. Поддержка Cast в этих ветках потребовала бы отдельного решения об
изменении поведения; она не является частью CR-01.

Дополнительно: вводная матрицы требует все строки при cache on/off, тогда как
M15 требует прогретый кеш и неизменное число построений metadata/newInstance.
Структурный критерий M15 нужно ограничить cache on; для cache off проверяется
эквивалентность результатов, а не отсутствие повторного поиска.

## IR-02. 030: не задана декларация для нетипизированного await

Приоритет P2, незавершённая публичная сигнатура/ветка алгоритма.

[Контракт 030](../../completed/pln-030-continuation-errors/contracts.md#критерий-готовности)
требует `resolve(ExecutionResult, ContinuationResult)`, у ContinuationResult
обязательный `string $finalType`. Одновременно раздел «Финал» и C19 допускают
`finalType = null` и возврат payload. `resolveFromStartResult()` сохраняет
необязательный sourceRequest и nullable finalTypeOverride.

Конкретный вход: ResultHandle создан без ContinuationResult на запросе, в клиенте
есть resolver; вызывается await() без целевого типа. Resolver выбран, но объект
декларации для его второго аргумента не определён. Синтетическая декларация описана
только для awaitByTokenAs, где finalType — строка.

Нужно явно выбрать представление отсутствующей декларации/типа: например,
nullable finalType синтетической декларации либо nullable declaration в интерфейсе
resolver. Недокументированная пустая строка/фиктивный класс не должны становиться
скрытой частью публичного контракта. Добавить эту ветку в C19 и сохранить одинаковое
решение в контракте и ADR. Это замечание к проекту, не новый runtime-дефект.

## IR-03. 030: внешний тип ошибки scalar Ready расходится с C20

Приоритет P2, локальное противоречие формулировок.

В разделе [«Финал»](../../completed/pln-030-continuation-errors/contracts.md#финал)
пункт 2 задаёт HydrationException для scalar payload, а оборачивание описано только
в пункте 3 после вызова гидратора. C20 ожидает ContinuationAwaitException с
final_hydration_failed. Общий замысел и ADR поддерживают именно второй результат.

Нужно явно охватить одним правилом оборачивания и проверку формы перед hydrate,
и исключение самого hydrate. Проверить обычное и cached awaitAs, path/previous,
lastResult и attempts. Нового продуктового выбора здесь не требуется.

## IR-04. 028: literal default допускает неоговорённые объекты внутри массива

Приоритет P2, пробел домена публичного descriptor и его приёмки.

[DefaultSpec::value](../../current/pln-028-declarative-dto/contracts.md#1-публичные-типы-и-подключение-в01)
принимает array без описанного ограничения элементов. Рекурсивный запрет объектов
записан только для HandlerSpec.args. Поэтому для `DefaultSpec::value([new Counter()])`
не определено, будет ли ConfigurationException, намеренно общий объект или фабрика
свежих значений. Readonly оболочка массива не решает вопрос.

Это важно после CR-01: фиксированный объект внутри внешнего literal default нельзя
исправить пересозданием ReflectionAttribute. 031 не содержит механизма для такого
descriptor, а набор 028 объявлен неизменяемым.

Рекомендуемое уточнение: применить к содержимому массива literal default тот же
рекурсивный запрет изменяемых объектов, что к HandlerSpec.args; для новых объектов
использовать provider. Добавить
проверку вложенного объекта рядом с A05 и проверить два применения одного набора.
Нельзя утверждать, что будущий DefaultSpec уже содержит дефект: его реализации нет;
нужно закрыть неоднозначность контракта.

## IR-05. 028 B: граница receiver с преобразованием целого значения

Приоритет P2, открытое сочетание выбранных публичных правил.

[Раздел 10](../../current/pln-028-declarative-dto/contracts.md#10-исходящее-представление-receiver-в12)
гарантирует исключение receiver во всех частях и на любом уровне. При этом остальные
свойства должны сериализоваться как прежде. Запрет атрибутов относится к самому
receiver, но не к свойству запроса, содержащему DTO.

Пример: request.payload содержит DTO с id/extra; на payload стоят BodyRoot и
Cast(JsonCast::class). [SerializationValueResolver](../../../src/Serialization/SerializationValueResolver.php)
вызывает cast до обхода DTO и сразу возвращает его результат. JsonCast превращает
весь объект в строку, включая extra; после этого класс и границы свойств недоступны.
Этот существующий путь воспроизведён публичным Serializer. Набора правил в нынешней
реализации нет, поэтому результат доказывает существование границы преобразования,
а не ошибку ещё не написанной фильтрации receiver.

Аналогичные границы — пользовательские casts целого значения, toArray/jsonSerialize,
Stringable в header/path. Простое чтение набора внутри Serializer не определяет,
что передавать таким обработчикам: исходный DTO или уже отфильтрованное представление.
Это меняет тип/данные аргумента и потому не является свободной внутренней деталью.

Нужно записать порядок, поддержанные сочетания и поведение неподдержанных. Например,
для первой версии сохранять гарантию исключения и отклонять непрозрачное преобразование
DTO с receiver до отправки; либо задать явный контракт безопасного преобразования.
Добавить случаи в B12–B17, включая отсутствие нового вызова конструктора DTO и
неизменность исходного объекта. Сам выбор «receiver не отправляется» пересматривать
не требуется; уточняется его взаимодействие с существующими расширениями.

## Неблокирующая гигиена

- В questions.md статус корректно снят без отправки, но нижний текст всё ещё называет
  первый вариант текущей рекомендацией и обещает будущую запись ответа. Его следует
  обозначить как прежний текст вопроса либо согласовать с принятым вторым вариантом.
- В readiness сохранён старый обзор под явной пометкой истории проверки. Это не
  отсутствие contracts.md сейчас; оснований повторно требовать уже созданные документы нет.
- Для maxAttempts полезно добавить числовую границу Auto/Async при значении 1:
  количество HTTP polling-запросов и attempts, включающий оценку старта, различаются.

## Воспроизведение IR-01 и границы IR-05

Запустить из корня через `php` со следующим stdin. Это диагностический пример,
не изменение кода пакета и не реализация правил 028.

```php
<?php
declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\AttributeMetadataCache;
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Casts\JsonCast;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Serialization\Serializer;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\CountingCast;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\MutableCounter;

require 'vendor/autoload.php';
require '.workflow/completed/pln-031-metadata-value-isolation/artifacts/Fixtures/MutableCounter.php';
require '.workflow/completed/pln-031-metadata-value-isolation/artifacts/Fixtures/CountingCast.php';

#[Get('/items/{id}')]
final class HeaderPathProbe extends AbstractRequest
{
    #[Header('X-Probe')]
    #[Cast(CountingCast::class, new MutableCounter())]
    public int $header = 0;

    #[Path('id')]
    #[Cast(CountingCast::class, new MutableCounter())]
    public int $id = 0;
}

final readonly class ExtrasProbeDto
{
    public function __construct(public int $id = 7, public array $extra = ['future' => false]) {}
}

#[Post('/body')]
final class EncodedDtoProbe extends AbstractRequest
{
    public function __construct(
        #[BodyRoot]
        #[Cast(JsonCast::class)]
        public ExtrasProbeDto $payload = new ExtrasProbeDto(),
    ) {}
}

$observations = [];
foreach ([false, true] as $enabled) {
    $serializer = new Serializer(new CastRegistry(), new AttributeMetadataCache($enabled));
    for ($i = 0; $i < 2; $i++) {
        $request = new HeaderPathProbe();
        $prepared = $serializer->serialize($request, new PipelineContext($request, new ClientConfig(baseUrl: 'https://probe.test'), 'probe'));
        $observations[$enabled ? 'on' : 'off'][] = ['header' => $prepared->headers['X-Probe'], 'url' => $prepared->url];
    }
}
$serializer = new Serializer(new CastRegistry());
$request = new EncodedDtoProbe();
$prepared = $serializer->serialize($request, new PipelineContext($request, new ClientConfig(baseUrl: 'https://probe.test'), 'probe'));
$observations['whole_dto_json_cast_body'] = $prepared->body;
echo json_encode($observations, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
```
