<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Request;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use Brahmic\ApiSutra\Enums\Pagination\PaginationMode;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Pagination\PaginationRule;
use Brahmic\ApiSutra\Pagination\Paginator;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Closure;

/**
 * Разрешает выполнение запроса с учётом правил пагинации.
 *
 * Инварианты:
 * - правило пагинации выбирается по приоритету: runtime override -> ClientConfig.paginationRule;
 * - для single-режима запрос уходит напрямую в executor без Paginator;
 * - для paginated-режимов без PaginableInterface бросается ConfigurationException.
 *
 * @see docs/guides/pagination.md
 * @see docs/guides/requests.md
 * @see docs/technical/execution.md
 */
final readonly class RequestResolver
{
    private readonly Closure $executor;

    public function __construct(
        private ClientConfig $config,
        callable $executor,
    ) {
        $this->executor = $executor instanceof Closure
            ? $executor
            : Closure::fromCallable($executor);
    }

    public function resolve(RequestInterface $request): ExecutionResult
    {
        $execution = null;
        $options = null;

        if ($request instanceof RequestExecutionInterface) {
            $execution = $request;
            $options = $request->getOptions();
            $request = $request->getRequest();
        }

        if ($options === null && $request instanceof RequestOptionsProviderInterface) {
            $options = $request->getOptions();
        }

        $rule = $this->resolveRule($options);

        if ($rule->isSingle()) {
            return ($this->executor)($execution ?? $request);
        }

        $paginator = $this->resolvePaginator($execution, $request);
        $paginator->failStrategy($rule->failStrategy);

        return match ($rule->mode) {
            PaginationMode::All => $paginator->all(),
            PaginationMode::Pages => $paginator->pages($rule->pages ?? 1),
            PaginationMode::Range => $paginator->range($rule->from ?? 1, $rule->to ?? 1),
            PaginationMode::Single => throw new ConfigurationException('Режим single не требует пагинации'),
        };
    }

    public function resolveRule(?RequestOptions $options): PaginationRule
    {
        return $options?->getPaginationRuleOverride()
            ?? $this->config->getPaginationRule();
    }

    private function resolvePaginator(?RequestExecutionInterface $execution, RequestInterface $request): Paginator
    {
        if ($execution !== null) {
            return new Paginator(
                request: $execution->getRequest(),
                options: $execution->getOptions(),
                paginationOptions: $execution->getPaginationOptions(),
                executor: $this->executor,
            );
        }

        if (!$request instanceof PaginableInterface) {
            throw new ConfigurationException('Запрос не поддерживает пагинацию');
        }

        $options = $request instanceof RequestOptionsProviderInterface
            ? $request->getOptions()
            : null;

        return new Paginator(
            request: $request,
            options: $options,
            executor: $this->executor,
        );
    }
}
