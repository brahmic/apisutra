<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Request;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaOverrideInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Pagination\DefaultPaginationMetaResolver;
use Brahmic\ApiSutra\Pagination\PaginationConfigResolver;
use Brahmic\ApiSutra\Pagination\Paginator;
use Brahmic\ApiSutra\Support\ArrayPath;
use Brahmic\ApiSutra\VO\Metadata\PaginationMeta;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use ReflectionProperty;

final readonly class RequestPaginationHelper
{
    public function __construct(
        private ?PipelineContext $context,
        private ?ClientInterface $client,
    ) {}

    public function paginate(AbstractRequest $request): Paginator
    {
        if (!$request instanceof PaginableInterface) {
            throw new ConfigurationException('Запрос не поддерживает пагинацию');
        }

        return new Paginator($request);
    }

    public function setPage(AbstractRequest $request, int $page): void
    {
        $param = $this->resolvePaginationParam($request, 'page');
        $this->setPaginationValue($request, $param, $page, true);
    }

    public function setLimit(AbstractRequest $request, int $limit): void
    {
        $param = $this->resolvePaginationParam($request, 'limit');
        $this->setPaginationValue($request, $param, $limit, true);
    }

    public function setCursor(AbstractRequest $request, ?string $cursor): void
    {
        $param = $this->resolvePaginationParam($request, 'cursor');
        if ($param !== null) {
            $this->setPaginationValue($request, $param, $cursor, false);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvePaginationOverrides(AbstractRequest $request, PaginationOptions $options): array
    {
        $overrides = [];
        if ($options->hasPage()) {
            $param = $this->resolvePaginationParam($request, 'page');
            if ($param !== null) {
                $overrides[$param] = $options->getPage();
            }
        }

        if ($options->hasLimit()) {
            $param = $this->resolvePaginationParam($request, 'limit');
            if ($param !== null) {
                $overrides[$param] = $options->getLimit();
            }
        }

        if ($options->hasCursor()) {
            $param = $this->resolvePaginationParam($request, 'cursor');
            if ($param !== null) {
                $overrides[$param] = $options->getCursor();
            }
        }

        return $overrides;
    }

    public function resolvePaginationConfig(AbstractRequest $request): PaginationConfig
    {
        $resolver = new PaginationConfigResolver($this->resolveClientConfig());
        return $resolver->resolve($request);
    }

    public function extractMeta(AbstractRequest $request, array $response): PaginationMeta
    {
        $config = $this->resolvePaginationConfig($request);
        $meta = $this->resolveMetaArray($response, $config);

        if ($request instanceof PaginationMetaOverrideInterface) {
            return $request->resolvePaginationMeta($response, $meta, $config, $this->context);
        }

        $resolver = $this->resolveMetaResolver($config);
        if ($resolver !== null) {
            return $resolver->resolve($request, $response, $meta, $config, $this->context);
        }

        return (new DefaultPaginationMetaResolver())->resolve($request, $response, $meta, $config, $this->context);
    }

    private function resolvePaginationParam(AbstractRequest $request, string $type): ?string
    {
        $config = $this->resolvePaginationConfig($request);

        return match ($type) {
            'page' => $config->pageParam,
            'limit' => $config->limitParam,
            'cursor' => $config->cursorParam,
            default => null,
        };
    }

    private function resolveClientConfig(): ?ClientConfig
    {
        if ($this->context !== null) {
            return $this->context->config;
        }

        return $this->client?->getConfig();
    }

    private function setPaginationValue(
        AbstractRequest $request,
        ?string $property,
        mixed $value,
        bool $required,
    ): void {
        if ($property === null) {
            return;
        }

        if (!property_exists($request, $property)) {
            if ($required) {
                throw new ConfigurationException("Свойство '{$property}' не найдено для пагинации");
            }
            return;
        }

        $reflection = new ReflectionProperty($request, $property);
        if ($reflection->isPublic()) {
            $request->{$property} = $value;
            return;
        }

        $reflection->setAccessible(true);
        $reflection->setValue($request, $value);
    }

    private function resolveMetaResolver(PaginationConfig $config): ?PaginationMetaResolverInterface
    {
        $resolver = $config->metaResolver;
        if ($resolver === null) {
            return null;
        }

        if (is_string($resolver)) {
            if (!class_exists($resolver)) {
                throw new ConfigurationException("Класс meta-resolver '{$resolver}' не найден");
            }
            $resolver = new $resolver();
        }

        if (!$resolver instanceof PaginationMetaResolverInterface) {
            throw new ConfigurationException('Meta-resolver должен реализовывать PaginationMetaResolverInterface');
        }

        return $resolver;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMetaArray(array $response, PaginationConfig $config): array
    {
        $meta = ArrayPath::getByPath($response, $config->metaPath);
        if (!is_array($meta)) {
            return [];
        }

        return $meta;
    }
}
