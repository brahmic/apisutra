# Request pipeline: Composite и DependsOn

Этот гайд про запросы, которые объединяют несколько запросов в одну операцию.
Они выполняются в рамках одного пайплайна и возвращают единый `ExecutionResult`
с вложенными результатами.

## CompositeRequest
**Задача:** выполнить набор независимых запросов и агрегировать результат.

Контракт:
- `requests(): RequestCollection`
- `aggregate(ResultCollection $results, PipelineContext $ctx): mixed`

### Пример
```php
use Brahmic\ApiSutra\Attributes\Behavior\Execution;
use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

#[Execution(mode: ExecutionMode::Parallel, failStrategy: FailStrategy::Partial)]
final class UserProfileComposite extends AbstractRequest implements CompositeRequestInterface
{
    public function __construct(
        public string $userId,
    ) {}

    public function requests(): RequestCollection
    {
        return RequestCollection::make([
            new GetUser($this->userId),
            new GetUserOrders($this->userId),
        ]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return [
            'user' => $results->get(GetUser::class)?->data,
            'orders' => $results->get(GetUserOrders::class)?->data,
        ];
    }
}
```

### Поведение
- `ExecutionMode` задаёт **sequential** или **parallel** для дочерних запросов.
- `FailStrategy::FailAll` — при любой ошибке composite возвращает FAILED.
- `FailStrategy::Partial/IgnoreErrors` — composite продолжает и возвращает PARTIAL/ SUCCESS.
- Результаты дочерних запросов доступны в `ExecutionResult::$nested`,
  мета‑информация — в `ExecutionResult::$meta` (BatchMeta).

## DependsOnRequest
**Задача:** выполнить зависимости, затем основной запрос.

Контракт:
- `dependencies(): RequestCollection`
- `processDependencies(ResultCollection $results, PipelineContext $ctx): void`

### Пример
```php
use Brahmic\ApiSutra\Contracts\Interfaces\Core\DependsOnRequestInterface;

final class CreateOrder extends AbstractRequest implements DependsOnRequestInterface
{
    public function __construct(
        public string $userId,
        public ?string $token = null,
    ) {}

    public function dependencies(): RequestCollection
    {
        return RequestCollection::make([
            new FetchUserToken($this->userId),
        ]);
    }

    public function processDependencies(ResultCollection $results, PipelineContext $ctx): void
    {
        $this->token = (string) ($results->get(FetchUserToken::class)?->data ?? '');
    }
}
```

### Поведение
- `DependsOn` **всегда выполняется sequential**, даже если задан Parallel.
- `FailStrategy::FailAll` завершит выполнение, если зависимость упала.
- После `processDependencies()` основной запрос отправляется как обычный.

## Роль RequestRole
Внутри пайплайна роль запроса отражает контекст выполнения:
- `Root` — основной запрос
- `Nested` — дочерний запрос composite
- `Dependency` — запрос зависимости

Роль используется в audit‑логах, debug‑данных и хуках.

## Замена подготовленного HTTP-тела

В hook до первой отправки присваивайте контексту новую копию:
`$context->preparedRequest = $context->preparedRequest->withBody($text)` либо
`->withStream($stream)`. Для удаления используйте `->withoutBody()`;
`with(body: null)` и `with(stream: null)` сохраняют прежнее значение.
При смене формата явно обновляйте Content-Type. Полный контракт, пример hook и
миграция — в [руководстве транспорта](transport.md#замена-и-очистка-тела-preparedrequest).

При включённом debug итоговый результат отражает фактически отправленный запрос
полученного ответа, включая последнюю retry-попытку. При сбое без ответа используется
актуальный prepared request контекста. Изменение контекста в `AfterResponse` не
подменяет отправленное тело в debug; redaction сохраняется. Замена тела после
первой попытки останавливает повтор с `body_changed`.
