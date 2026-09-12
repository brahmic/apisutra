<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Hydration;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\PaginationConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsContainerInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Extensions\ExtensionRegistry;
use Brahmic\ApiSutra\Pagination\PaginationConfigResolver;
use Brahmic\ApiSutra\Pagination\PaginationItemsCollectionBuilder;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Support\ArrayPath;
use Brahmic\ApiSutra\VO\Files\FileResponse;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Pipeline\DecodedResponse;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use Throwable;

/**
 * Гидрация ответа и пагинации.
 * Нюансы:
 * - Для paginated-запросов с #[Returns] возвращается DTO-обёртка.
 * - Items извлекаются по itemsPath и типизируются через itemsType.
 */
final readonly class ResponseHydrator
{
    public function __construct(
        private ClientConfig $config,
        private Hydrator $hydrator,
        private ExtensionRegistry $extensions,
    ) {
    }

    public function decodeResponse(RequestInterface $request, PipelineContext $context): DecodedResponse
    {
        if ($this->isDownloadRequest($request)) {
            return new DecodedResponse([]);
        }

        $handler = $this->extensions->resolveResponseHandler($context->response, $context);
        if ($handler !== null) {
            // Расширение владеет форматом; строгий JSON применяется только при отказе обработчика.
            return new DecodedResponse($context->response?->json() ?? [], $handler);
        }

        return new DecodedResponse($this->decodeStandardResponse($request, $context->response));
    }

    public function hydrateResponse(
        RequestInterface $request,
        PipelineContext $context,
        mixed $data,
        ?DecodedResponse $decoded = null,
    ): mixed {
        if ($this->isDownloadRequest($request)) {
            return $this->makeFileResponse($context->response);
        }

        $handler = $decoded !== null
            ? $decoded->handler
            : $this->extensions->resolveResponseHandler($context->response, $context);
        $extensionResult = $handler?->handle($context->response, $context);
        if ($extensionResult !== null) {
            return $extensionResult;
        }

        if (($decoded === null && $context->response !== null) || $handler !== null) {
            $standardData = $this->decodeStandardResponse($request, $context->response);
            if (!is_array($standardData)) {
                $data = $standardData;
            }
        }

        $dtoClass = $this->resolveDtoClass($request);
        $returns = $this->resolveReturnsAttribute($request);

        if ($request instanceof AbstractRequest && $request instanceof PaginableInterface) {
            if (!is_array($data)) {
                throw new HydrationException('Ответ пагинации должен содержать JSON-массив или объект');
            }
            $pagination = $this->resolvePaginationConfig($request);

            // Режим контейнера: #[Returns] сохраняет структуру ответа, items подставляются отдельно
            if ($returns !== null) {
                $items = $this->extractPaginationItems($data, $pagination);
                $items = $this->hydratePaginationItems($items, $pagination, $context);
                $items = $this->buildItemsCollection($items, $pagination);

                [$data, $dtoClass] = $this->applyUnwrap($data, $returns, $dtoClass);
                $container = $this->hydrateIfNeeded($data, $dtoClass, $context);

                if (!$container instanceof PaginationItemsContainerInterface) {
                    throw new ConfigurationException('DTO-контейнер пагинации должен реализовывать PaginationItemsContainerInterface');
                }

                return $container->withItems($items);
            }

            // Режим items-only: возвращаем только items
            $data = $this->applyPaginationItemsOnly($data, $pagination);
        }

        [$data, $dtoClass] = $this->applyUnwrap($data, $returns, $dtoClass);

        return $this->hydrateIfNeeded($data, $dtoClass, $context);
    }

    private function makeFileResponse(?ProviderResponse $response): FileResponse
    {
        if ($response === null) {
            throw new ConfigurationException('Нет ответа для загрузки файла');
        }

        $stream = $response->stream ?? Psr7Utils::streamFor($response->body);
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $contentType = $response->header('Content-Type');
        $contentDisposition = $response->header('Content-Disposition');

        $filename = null;
        if (is_string($contentDisposition) && preg_match('/filename="?([^"]+)"?/i', $contentDisposition, $matches)) {
            $filename = $matches[1] ?? null;
        }

        return new FileResponse(
            stream: $stream,
            filename: $filename,
            mimeType: $contentType,
            size: $stream->getSize(),
            config: $this->config,
        );
    }

    private function isDownloadRequest(RequestInterface $request): bool
    {
        return $request instanceof AbstractRequest && $request->hasDownload();
    }

    private function decodeStandardResponse(RequestInterface $request, ?ProviderResponse $response): mixed
    {
        $requiresArray = $this->resolveDtoClass($request) !== null || $request instanceof PaginableInterface;
        if ($response === null || $response->status === 204 || $response->body === '') {
            return $requiresArray ? [] : null;
        }

        $contentType = strtolower(trim(explode(';', $response->header('Content-Type') ?? '')[0]));
        if ($contentType === '' || $contentType === 'application/json' || str_ends_with($contentType, '+json')) {
            $data = $response->jsonStrict();
            return $requiresArray && $data === null ? [] : $data;
        }

        if (!$requiresArray && $contentType === 'text/plain') {
            return $response->body;
        }

        // Остальные форматы сохраняют прежнее поведение и могут обрабатываться расширениями.
        return $response->json() ?? [];
    }

    private function resolveDtoClass(RequestInterface $request): ?string
    {
        return $request instanceof AbstractRequest ? $request->getResponseType() : null;
    }

    private function resolveReturnsAttribute(RequestInterface $request): ?object
    {
        return $request instanceof AbstractRequest ? $request->getReturnsAttribute() : null;
    }

    private function applyPaginationItemsOnly(mixed $data, PaginationConfig $pagination): mixed
    {
        if ($pagination->itemsPath === '') {
            return $data;
        }

        $items = ArrayPath::getByPath($data, $pagination->itemsPath);
        return $items !== null ? $items : $data;
    }

    /**
     * @param object|null $returns
     * @return array{0: mixed, 1: ?string}
     */
    private function applyUnwrap(mixed $data, ?object $returns, ?string $dtoClass): array
    {
        if ($returns === null || $returns->unwrap === null) {
            return [$data, $dtoClass];
        }

        $unwrapped = ArrayPath::getByPathWithStatus($data, $returns->unwrap);
        if ($unwrapped->isMissing()) {
            throw HydrationException::invalidValue('unwrap_path_missing', 'present', 'missing', $returns->unwrap);
        }

        $dtoClass = $returns->type ?? $dtoClass;

        return [$unwrapped->value, $dtoClass];
    }

    private function hydrateIfNeeded(mixed $data, ?string $dtoClass, PipelineContext $context): mixed
    {
        if ($dtoClass === null) {
            return $data;
        }

        $path = $this->resolveReturnsAttribute($context->request)?->unwrap;
        if (!is_array($data) && !is_object($data)) {
            throw HydrationException::invalidValue('unexpected_response_shape', $dtoClass, get_debug_type($data), $path ?? '$');
        }

        try {
            return $this->hydrator->hydrate($data, $dtoClass, $context);
        } catch (HydrationException $exception) {
            throw $path === null ? $exception : $exception->prependPath($path);
        } catch (SdkException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new HydrationException('Не удалось гидратировать ответ в ' . $dtoClass, 0, $exception);
        }
    }

    private function resolvePaginationConfig(AbstractRequest $request): PaginationConfig
    {
        $resolver = new PaginationConfigResolver($this->config);
        return $resolver->resolve($request);
    }

    /**
     * @return array<int, mixed>
     */
    private function extractPaginationItems(mixed $data, PaginationConfig $pagination): array
    {
        if (!is_array($data)) {
            return [];
        }

        if ($pagination->itemsPath === '') {
            return $data;
        }

        $items = ArrayPath::getByPath($data, $pagination->itemsPath);
        return is_array($items) ? $items : [];
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    private function hydratePaginationItems(array $items, PaginationConfig $pagination, PipelineContext $context): array
    {
        if ($pagination->itemsType === null) {
            return $items;
        }

        try {
            return $this->hydrator->hydrateCollection($items, $pagination->itemsType, $context);
        } catch (HydrationException $exception) {
            throw $exception->prependPath($pagination->itemsPath === '' ? '$' : $pagination->itemsPath);
        } catch (SdkException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new HydrationException('Не удалось гидратировать элементы ответа в ' . $pagination->itemsType, 0, $exception);
        }
    }

    /**
     * @param array<int, mixed> $items
     */
    private function buildItemsCollection(array $items, PaginationConfig $pagination): array|object
    {
        $builder = new PaginationItemsCollectionBuilder();
        return $builder->build($items, $pagination);
    }
}
