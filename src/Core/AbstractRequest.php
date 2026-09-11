<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Core;

use Brahmic\ApiSutra\Contracts\Interfaces\Attributes\AttributeMetadataCacheProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientResolverInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Validation\ValidatableInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Enums\Execution\SendMode;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Request\RequestExecution;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\Request\RequestOptionsChainTrait;
use Brahmic\ApiSutra\Request\RequestPaginationHelper;
use Brahmic\ApiSutra\Request\RequestSpec;
use Brahmic\ApiSutra\Request\RequestSpecResolver;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;
use Brahmic\ApiSutra\Result\ResultHandle;
use Brahmic\ApiSutra\Traits\DefaultRequestFailurePolicyTrait;
use Brahmic\ApiSutra\Traits\RequestHooksBridgeTrait;
use Brahmic\ApiSutra\Traits\RequestOptionsAccessorsTrait;
use Brahmic\ApiSutra\Traits\RequestSpecAccessorsTrait;
use Brahmic\ApiSutra\Traits\ValidatesAttributes;
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\PromiseInterface;
use Throwable;

abstract class AbstractRequest implements RequestInterface, ValidatableInterface, RequestOptionsProviderInterface
{
    use ValidatesAttributes;
    use DefaultRequestFailurePolicyTrait;
    use RequestOptionsChainTrait;
    use RequestSpecAccessorsTrait;
    use RequestOptionsAccessorsTrait;
    use RequestHooksBridgeTrait;

    private ?RequestSpecResolver $specResolver = null;
    private ?RequestSpec $spec = null;
    private ?RequestPaginationHelper $paginationHelper = null;
    private ?RequestOptions $options = null;

    protected ?ClientInterface $client = null;
    protected ?PipelineContext $context = null;


    /**
     * Синхронная отправка
     */
    public function send(SendMode $mode = SendMode::Sync): ResultHandle
    {
        return $mode === SendMode::Async
            ? $this->resolveClient()->sendAsync($this)
            : $this->resolveClient()->send($this);
    }

    /**
     * Асинхронная отправка
     */
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

    public function setClient(ClientInterface $client): static
    {
        $this->assertClientOwnership($client);
        $this->client = $client;
        $this->resetPaginationHelper();
        $this->resetSpecCache();
        return $this;
    }

    public function setContext(PipelineContext $context): void
    {
        $this->context = $context;
        $this->resetPaginationHelper();
    }

    public function __clone()
    {
        $this->resetSpecCache();
        $this->resetPaginationHelper();
    }

    public function getContext(): ?PipelineContext
    {
        return $this->context;
    }

    public function hasClient(): bool
    {
        return $this->client !== null;
    }

    public function isRoot(): bool
    {
        return $this->context?->role === RequestRole::Root;
    }

    protected function resolveClient(): ClientInterface
    {
        if ($this->client === null) {
            $resolver = $this->resolveClientResolver();
            if ($resolver !== null) {
                $this->client = $resolver->resolve($this);
            }
        }

        if ($this->client === null) {
            throw new ConfigurationException('Клиент не установлен для запроса');
        }

        return $this->client;
    }

    public function getClient(): ClientInterface
    {
        return $this->resolveClient();
    }

    protected function resolveEndpoint(): ?string
    {
        return null;
    }

    protected function resolveBaseUrl(): ?string
    {
        return null;
    }

    private function options(): RequestOptions
    {
        if ($this->options === null) {
            $this->options = RequestOptions::empty();
        }

        return $this->options;
    }

    private function resetSpecCache(): void
    {
        $this->specResolver = null;
        $this->spec = null;
    }

    private function resetPaginationHelper(): void
    {
        $this->paginationHelper = null;
    }

    private function assertClientOwnership(ClientInterface $client): void
    {
        $resolver = $this->resolveClientResolver();
        if ($resolver === null) {
            return;
        }

        $resolver->assertOwnership($client, $this);
    }

    private function resolveClientResolver(): ?ClientResolverInterface
    {
        $provider = $this->resolveContainerProvider();
        if (!$provider->bound(ClientResolverInterface::class)) {
            return null;
        }

        $resolver = $provider->make(ClientResolverInterface::class);
        return $resolver instanceof ClientResolverInterface ? $resolver : null;
    }

    private function resolveContainerProvider(): ContainerProviderInterface
    {
        $provider = $this->client?->getConfig()->containerProvider;
        return ContainerProviderRegistry::resolve($provider);
    }

    private function spec(): RequestSpec
    {
        if ($this->spec === null) {
            $this->spec = $this->specResolver()->resolve($this);
        }

        return $this->spec;
    }

    private function specResolver(): RequestSpecResolver
    {
        if ($this->specResolver === null) {
            $metadataCache = null;
            if ($this->client instanceof AttributeMetadataCacheProviderInterface) {
                $metadataCache = $this->client->getAttributeMetadataCache();
            }
            $this->specResolver = new RequestSpecResolver($metadataCache);
        }

        return $this->specResolver;
    }

    protected function cloneWith(callable $mutate): static
    {
        $clone = clone $this;
        $mutate($clone);
        return $clone;
    }

    protected function currentOptions(): RequestOptions
    {
        return $this->options();
    }

    protected function executionFromOptions(RequestOptions $options): RequestExecutionInterface
    {
        return new RequestExecution($this, $options);
    }


    public function clearCache(): void
    {
        $this->resolveClient()->clearCacheForRequest($this);
    }

    protected function paginationHelper(): RequestPaginationHelper
    {
        if ($this->paginationHelper === null) {
            $this->paginationHelper = new RequestPaginationHelper($this->context, $this->client);
        }

        return $this->paginationHelper;
    }


    protected static function validationMessages(): array
    {
        return [];
    }

    public function hasRequestFailedInternal(ProviderResponse $response): bool
    {
        return $this->hasRequestFailed($response);
    }

    public function shouldRetryInternal(ProviderResponse $response, int $attempt): bool
    {
        return $this->shouldRetry($response, $attempt);
    }

    public function getRequestExceptionInternal(ProviderResponse $response): ?Throwable
    {
        return $this->getRequestException($response);
    }
}
