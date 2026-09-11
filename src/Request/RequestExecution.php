<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Request;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Execution\SendMode;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Pagination\Paginator;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;
use Brahmic\ApiSutra\Result\ResultHandle;
use GuzzleHttp\Promise\PromiseInterface;

final readonly class RequestExecution implements RequestExecutionInterface
{
    use RequestOptionsChainTrait;

    public function __construct(
        private AbstractRequest $request,
        private RequestOptions $options,
        ?PaginationOptions $paginationOptions = null,
    ) {
        $this->paginationOptions = $paginationOptions ?? PaginationOptions::empty();
    }

    private PaginationOptions $paginationOptions;

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getOptions(): RequestOptions
    {
        return $this->options;
    }

    public function getPaginationOptions(): PaginationOptions
    {
        return $this->paginationOptions;
    }

    public function send(SendMode $mode = SendMode::Sync): ResultHandle
    {
        return $mode === SendMode::Async
            ? $this->request->getClient()->sendAsync($this)
            : $this->request->getClient()->send($this);
    }

    public function sendAsync(): ResultHandle
    {
        return $this->send(SendMode::Async);
    }

    public function resolved(): ResolvedResultInterface
    {
        return $this->send()->resolved();
    }

    public function resolvedAsync(): PromiseInterface
    {
        return $this->send()->resolvedAsync();
    }

    public function dataOrFail(): mixed
    {
        return $this->send()->dataOrFail();
    }

    public function clearCache(): void
    {
        $this->request->getClient()->clearCacheForRequest($this);
    }

    public function paginate(): Paginator
    {
        if (!$this->request instanceof PaginableInterface) {
            throw new ConfigurationException('Запрос не поддерживает пагинацию');
        }

        return new Paginator($this->request, $this->options, $this->paginationOptions);
    }

    public function getMethod(): HttpMethod
    {
        return $this->request->getMethod();
    }

    public function getEndpoint(): string
    {
        return $this->request->getEndpoint();
    }

    public function getResponseType(): ?string
    {
        return $this->request->getResponseType();
    }

    public function withPage(int $page): self
    {
        if (!$this->request instanceof PaginableInterface) {
            throw new ConfigurationException('Запрос не поддерживает пагинацию');
        }

        return new self(
            request: $this->request,
            options: $this->options,
            paginationOptions: $this->paginationOptions->withPage($page),
        );
    }

    public function withLimit(int $limit): self
    {
        if (!$this->request instanceof PaginableInterface) {
            throw new ConfigurationException('Запрос не поддерживает пагинацию');
        }

        return new self(
            request: $this->request,
            options: $this->options,
            paginationOptions: $this->paginationOptions->withLimit($limit),
        );
    }

    public function withCursor(?string $cursor): self
    {
        if (!$this->request instanceof PaginableInterface) {
            throw new ConfigurationException('Запрос не поддерживает пагинацию');
        }

        return new self(
            request: $this->request,
            options: $this->options,
            paginationOptions: $this->paginationOptions->withCursor($cursor),
        );
    }

    protected function currentOptions(): RequestOptions
    {
        return $this->options;
    }

    protected function executionFromOptions(RequestOptions $options): RequestExecutionInterface
    {
        return new self($this->request, $options, $this->paginationOptions);
    }
}
