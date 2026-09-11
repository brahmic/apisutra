<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pagination;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsContainerInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Request\PaginationOptions;
use Brahmic\ApiSutra\Request\RequestExecution;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Result\PaginatedResult;
use Brahmic\ApiSutra\VO\Errors\RequestError;
use Brahmic\ApiSutra\VO\Errors\SystemErrorContextBuilder;
use Brahmic\ApiSutra\VO\Metadata\PaginationMeta;
use Closure;
use IteratorAggregate;
use Traversable;

/**
 * Итератор пагинации с защитой от бесконечных циклов.
 *
 * Нюансы:
 * - Если в meta есть total/perPage, количество страниц вычисляется автоматически.
 * - Для cursor-based важно, чтобы nextCursor менялся; иначе пагинация прерывается.
 * - Для ручного ограничения можно использовать pages()/range().
 * - Дополнительный жёсткий лимит задаётся через PaginationConfig.maxPages.
 * - При DTO-обёртке items извлекаются через PaginationItemsContainerInterface.
 */
final class Paginator implements IteratorAggregate
{
    private int $page = 1;
    private ?int $limit = null;
    private ?string $cursor = null;
    private FailStrategy $failStrategy = FailStrategy::FailAll;
    private bool $paginationConfigResolved = false;
    private ?PaginationConfig $paginationConfig = null;

    public function __construct(
        private readonly AbstractRequest& PaginableInterface $request,
        private readonly ?RequestOptions $options = null,
        ?PaginationOptions $paginationOptions = null,
        private readonly ?Closure $executor = null,
    ) {
        if ($paginationOptions?->hasPage()) {
            $this->page = $paginationOptions->getPage() ?? 1;
        }
        if ($paginationOptions?->hasLimit()) {
            $this->limit = $paginationOptions->getLimit();
        }
        if ($paginationOptions?->hasCursor()) {
            $this->cursor = $paginationOptions->getCursor();
        }
    }

    public function perPage(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function failStrategy(FailStrategy $strategy): self
    {
        $this->failStrategy = $strategy;
        return $this;
    }

    public function all(): PaginatedResult
    {
        return $this->run(fn () => true);
    }

    public function pages(int $count): PaginatedResult
    {
        return $this->run(fn (int $page) => $page <= $count);
    }

    public function range(int $from, int $to): PaginatedResult
    {
        $this->page = $from;
        return $this->run(fn (int $page) => $page <= $to);
    }

    #[\Override]
    public function getIterator(): Traversable
    {
        $page = $this->page;
        $cursor = $this->cursor;
        $config = $this->getPaginationConfig();
        $lastMetaPage = null;
        $iterations = 0;
        $maxPages = $config->maxPages;

        while (true) {
            if ($this->isMaxPagesReached($maxPages, $iterations)) {
                return;
            }

            $result = $this->execute($this->buildExecution($page, $cursor, $config));
            $iterations++;
            yield $result;

            if ($result->isFailed()) {
                return;
            }

            $meta = $this->resolveMeta($result);
            if (!$meta->hasMore) {
                return;
            }

            // Guard: проверяем условия продолжения пагинации
            if ($this->hasGuardViolation($meta, $page, $config, $cursor, $lastMetaPage)) {
                return;
            }

            $lastMetaPage = $meta->currentPage;
            $cursor = $meta->nextCursor;
            $page++;
        }
    }

    private function run(callable $condition): PaginatedResult
    {
        $results = [];
        $items = [];
        $errors = [];
        $meta = null;
        $page = $this->page;
        $cursor = $this->cursor;
        $config = $this->getPaginationConfig();
        $lastMetaPage = null;
        $iterations = 0;
        $maxPages = $config->maxPages;

        while ($condition($page)) {
            if ($this->isMaxPagesReached($maxPages, $iterations)) {
                $errors[] = $this->buildGuardError('достигнут лимит страниц', $page);
                break;
            }

            $result = $this->execute($this->buildExecution($page, $cursor, $config));
            $iterations++;
            $results[] = $result;

            if ($result->isFailed()) {
                $traceId = $result->traceId ?? SystemErrorContextBuilder::resolveTraceId($this->request);
                $httpStatus = $result->errors->first()?->response?->status;
                $contextData = SystemErrorContextBuilder::build(
                    traceId: $traceId,
                    httpStatus: $httpStatus,
                    requestClass: $this->request::class,
                );

                $errors[] = new RequestError(
                    code: ErrorCode::ServerError,
                    message: 'Ошибка загрузки страницы',
                    requestClass: $this->request::class,
                    context: array_merge($contextData, ['page' => $page]),
                );

                if ($this->failStrategy === FailStrategy::FailAll) {
                    break;
                }

                // Для Partial: пропускаем guard-проверки, просто увеличиваем page
                $page++;
                continue;
            }

            $this->appendItems($items, $result->data);

            $meta = $this->resolveMeta($result);
            if (!$meta->hasMore) {
                break;
            }

            // Guard: проверяем условия продолжения пагинации
            if ($this->hasGuardViolation($meta, $page, $config, $cursor, $lastMetaPage)) {
                $error = $this->buildPaginationGuardError($meta, $page, $config, $cursor, $lastMetaPage);
                if ($error !== null) {
                    $errors[] = $error;
                }
                break;
            }

            $lastMetaPage = $meta->currentPage;
            $cursor = $meta->nextCursor;
            $page++;
        }

        $status = match (true) {
            $errors === [] => ResultStatus::SUCCESS,
            count($items) > 0 => ResultStatus::PARTIAL,
            default => ResultStatus::FAILED,
        };

        $items = $this->buildItemsCollection($items, $config);

        return new PaginatedResult(
            data: $items,
            status: $status,
            errors: new ErrorCollection($errors),
            meta: $meta,
            nested: $results,
        );
    }

    private function resolveMeta(ExecutionResult $result): PaginationMeta
    {
        if ($result->meta instanceof PaginationMeta) {
            return $result->meta;
        }

        $data = $result->debug?->response?->json() ?? [];
        return $this->request->extractMeta($data);
    }

    private function reachedTotalPages(PaginationMeta $meta, int $page): bool
    {
        $totalPages = $meta->totalPages();
        if ($totalPages === null) {
            return false;
        }

        return $page >= $totalPages;
    }

    private function isCursorBased(PaginationConfig $config, ?string $cursor, ?string $nextCursor): bool
    {
        return $config->cursorParam !== null || $cursor !== null || $nextCursor !== null;
    }

    private function hasCursorProgress(?string $cursor, ?string $nextCursor): bool
    {
        return $nextCursor !== null && $nextCursor !== $cursor;
    }

    private function hasPageProgress(?int $lastMetaPage, PaginationMeta $meta): bool
    {
        if ($meta->perPage <= 0) {
            return true;
        }

        if ($lastMetaPage === null) {
            return true;
        }

        return $meta->currentPage > $lastMetaPage;
    }

    private function buildGuardError(string $reason, int $page): RequestError
    {
        $contextData = SystemErrorContextBuilder::build(
            traceId: SystemErrorContextBuilder::resolveTraceId($this->request),
            httpStatus: null,
            requestClass: $this->request::class,
        );

        return new RequestError(
            code: ErrorCode::ServerError,
            message: 'Пагинация остановлена: ' . $reason,
            requestClass: $this->request::class,
            context: array_merge($contextData, ['page' => $page]),
        );
    }

    /**
     * Проверяет, нарушены ли условия продолжения пагинации (guard).
     * Возвращает true, если пагинация должна остановиться.
     */
    private function hasGuardViolation(
        PaginationMeta $meta,
        int $page,
        PaginationConfig $config,
        ?string $cursor,
        ?int $lastMetaPage
    ): bool {
        // Guard: останавливаемся, если достигнут расчётный предел страниц
        if ($this->reachedTotalPages($meta, $page)) {
            return true;
        }

        // Guard: проверяем прогресс по cursor/page
        if ($this->isCursorBased($config, $cursor, $meta->nextCursor)) {
            return !$this->hasCursorProgress($cursor, $meta->nextCursor);
        }

        return !$this->hasPageProgress($lastMetaPage, $meta);
    }

    /**
     * Создаёт ошибку guard-нарушения, если требуется (для метода run).
     * Возвращает null для естественного завершения (достигнут totalPages).
     */
    private function buildPaginationGuardError(
        PaginationMeta $meta,
        int $page,
        PaginationConfig $config,
        ?string $cursor,
        ?int $lastMetaPage
    ): ?RequestError {
        if ($this->reachedTotalPages($meta, $page)) {
            return null; // естественное завершение, не ошибка
        }

        if ($this->isCursorBased($config, $cursor, $meta->nextCursor)) {
            if (!$this->hasCursorProgress($cursor, $meta->nextCursor)) {
                return $this->buildGuardError('cursor не изменился', $page);
            }
        } elseif (!$this->hasPageProgress($lastMetaPage, $meta)) {
            return $this->buildGuardError('page не изменился', $page);
        }

        return null;
    }

    private function resolvePage(int $page, PaginationConfig $config): int
    {
        if (!$config->offsetBased) {
            return $page;
        }

        if ($this->limit === null) {
            throw new ConfigurationException('Для offset-based пагинации требуется limit');
        }

        return ($page - 1) * $this->limit;
    }

    private function buildExecution(int $page, ?string $cursor, PaginationConfig $config): RequestExecutionInterface
    {
        // Если опции не заданы — стартуем с request, чтобы сохранить переопределения withPage/withLimit/withCursor.
        $execution = $this->options !== null
            ? new RequestExecution(
                request: $this->request,
                options: $this->options,
            )
            : $this->request;

        if ($this->limit !== null) {
            $execution = $execution->withLimit($this->limit);
        }

        $resolvedPage = $this->resolvePage($page, $config);
        $execution = $execution->withPage($resolvedPage);

        if ($cursor !== null) {
            $execution = $execution->withCursor($cursor);
        }

        return $execution;
    }

    private function execute(RequestExecutionInterface $execution): ExecutionResult
    {
        if ($this->executor !== null) {
            return ($this->executor)($execution);
        }

        if (method_exists($execution, 'rules')) {
            $execution = $execution->rules(PaginationRule::single());
        }

        return $execution->send()->raw();
    }

    private function appendItems(array &$items, mixed $data): void
    {
        $pageItems = $this->resolvePageItems($data);
        $items = array_merge($items, $pageItems);
    }

    private function getPaginationConfig(): PaginationConfig
    {
        if (!$this->paginationConfigResolved) {
            $this->paginationConfigResolved = true;
            $config = $this->request->getContext()?->config;
            if ($config === null) {
                try {
                    $config = $this->request->getClient()->getConfig();
                } catch (ConfigurationException) {
                    $config = null;
                }
            }

            $resolver = new PaginationConfigResolver($config);
            $this->paginationConfig = $resolver->resolve($this->request);
        }

        return $this->paginationConfig ?? new PaginationConfig();
    }

    private function isMaxPagesReached(?int $maxPages, int $iterations): bool
    {
        return $maxPages !== null && $maxPages > 0 && $iterations >= $maxPages;
    }

    /**
     * @return array<int, mixed>
     */
    private function resolvePageItems(mixed $data): array
    {
        if ($data instanceof PaginationItemsContainerInterface) {
            $data = $data->items();
        }

        $builder = new PaginationItemsCollectionBuilder();
        return $builder->normalizeToArray($data);
    }

    /**
     * @param array<int, mixed> $items
     */
    private function buildItemsCollection(array $items, PaginationConfig $config): array|object
    {
        $builder = new PaginationItemsCollectionBuilder();
        return $builder->build($items, $config);
    }
}
