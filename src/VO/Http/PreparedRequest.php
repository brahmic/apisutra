<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\VO\Http;

use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Psr\Http\Message\StreamInterface;

readonly class PreparedRequest
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public HttpMethod $method,
        public string $url,
        public array $headers = [],
        public ?string $body = null,
        public ?StreamInterface $stream = null,
        public array $meta = [],
    ) {}

    /**
     * Создать копию с изменениями
     */
    public function with(
        ?string $url = null,
        ?array $headers = null,
        ?string $body = null,
        ?StreamInterface $stream = null,
        ?array $meta = null,
    ): self {
        return new self(
            method: $this->method,
            url: $url ?? $this->url,
            headers: $headers ?? $this->headers,
            body: $body ?? $this->body,
            stream: $stream ?? $this->stream,
            meta: $meta ?? $this->meta,
        );
    }

    /**
     * Добавить header
     */
    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return $this->with(headers: $headers);
    }
}

