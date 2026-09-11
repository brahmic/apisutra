<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Laravel;

use Brahmic\ApiSutra\Contracts\Interfaces\Response\ClientResponseAdapterInterface;
use Brahmic\ApiSutra\Response\ClientResponse;
use Brahmic\ApiSutra\VO\Files\FileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Адаптер ClientResponse → Laravel Response.
 */
final readonly class ClientResponseAdapter implements ClientResponseAdapterInterface
{
    public function toResponse(ClientResponse $response): SymfonyResponse
    {
        $body = $response->body;
        if ($body instanceof FileResponse) {
            return $this->toFileResponse($response, $body);
        }

        if (is_string($body)) {
            return new Response($body, $response->status, $response->headers);
        }

        if ($body === null) {
            return new Response('', $response->status, $response->headers);
        }

        return new JsonResponse($body, $response->status, $response->headers);
    }

    private function toFileResponse(ClientResponse $response, FileResponse $file): StreamedResponse
    {
        $headers = $response->headers;
        $mimeType = $file->mimeType();
        if ($mimeType !== null && !$this->hasHeader($headers, 'Content-Type')) {
            $headers['Content-Type'] = $mimeType;
        }

        if (!$this->hasHeader($headers, 'Content-Disposition')) {
            $filename = $file->filename() ?? 'file';
            $headers['Content-Disposition'] = 'attachment; filename="' . $filename . '"';
        }

        $size = $file->size();
        if ($size !== null && !$this->hasHeader($headers, 'Content-Length')) {
            $headers['Content-Length'] = (string) $size;
        }

        $stream = $file->stream();
        $callback = static function () use ($stream): void {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            while (!$stream->eof()) {
                echo $stream->read(8192);
            }
        };

        return new StreamedResponse($callback, $response->status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        foreach ($headers as $key => $_value) {
            if (strcasecmp($key, $name) === 0) {
                return true;
            }
        }

        return false;
    }
}
