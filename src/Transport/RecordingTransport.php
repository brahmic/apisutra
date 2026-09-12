<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Transport;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Diagnostics\RedactionPolicy;
use Brahmic\ApiSutra\Files\FileTransferGuard;
use Brahmic\ApiSutra\Http\DestinationGuard;
use Brahmic\ApiSutra\Http\RequestDestination;
use Brahmic\ApiSutra\Testing\Fixture;
use Brahmic\ApiSutra\Testing\FixtureRedactor;
use Brahmic\ApiSutra\VO\Files\FileTransferOptions;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Brahmic\ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use ReflectionClass;
use Brahmic\ApiSutra\Exceptions\Testing\RecordingException;
use ErrorException;
use RuntimeException;
use UnexpectedValueException;
use Throwable;

final class RecordingTransport implements TimeoutAwareTransportInterface, DestinationAwareInterface, FileStreamingInterface
{
    public function assertSupportsFileTransfer(FileTransferOptions $options): void
    {
        FileTransferGuard::checkCapability($this->transport, $options);
    }

    public function assertSupportsDestination(RequestDestination $destination): void
    {
        DestinationGuard::checkCapability($this->transport, $destination);
    }

    public function assertSupportsTimeouts(TransportOptions $options): void
    {
        TransportCapabilities::check($this->transport, $options);
    }

    /**
     * @var array<string, Fixture>
     */
    private array $fixtures = [];
    private FixtureRedactor $redactor;

    /**
     * @param array<string, Fixture> $fixtures
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $path,
        array $fixtures = [],
        RedactionPolicy $redaction = new RedactionPolicy(),
    ) {
        $this->fixtures = $fixtures;
        $this->redactor = new FixtureRedactor($redaction);
    }

    #[\Override]
    public function send(PreparedRequest $request): ProviderResponse
    {
        DestinationGuard::checkRequest($request);
        FileTransferGuard::checkCapability($this, FileTransferGuard::options($request));
        DestinationGuard::checkCapability($this, $request->destination);
        $response = $this->transport->send($request);
        try {
            $this->record($request, $response);
        } catch (Throwable $exception) {
            throw new RecordingException($response, $exception);
        }
        return $response;
    }

    #[\Override]
    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        $promise = new Promise();

        try {
            $promise->resolve($this->send($request));
        } catch (Throwable $exception) {
            $promise->reject($exception);
        }

        return $promise;
    }

    private function record(PreparedRequest $request, ProviderResponse $response): void
    {
        foreach ([$request->body, $response->body] as $body) {
            if ($body !== null && preg_match('//u', $body) !== 1) {
                throw new UnexpectedValueException('Тело не представимо в UTF-8 формате fixture');
            }
        }
        $payload = [
            'request' => [
                'class' => $request->meta['requestClass'] ?? null,
                'method' => $request->method->value,
                'url' => $request->destination?->preserveUrl ? $request->destination->diagnosticUrl() : $request->url,
                'headers' => $request->headers,
                'body' => $this->normalizeBody($request->body, $request->headers['Content-Type'] ?? null),
                'bodyOmitted' => $request->stream !== null,
                'size' => $request->stream?->getSize(),
            ],
            'response' => [
                'status' => $response->status,
                'headers' => $response->headers,
                'body' => $this->normalizeBody($response->body, $response->header('Content-Type')),
                'bodyOmitted' => $response->stream !== null,
                'size' => $response->stream?->getSize(),
            ],
            'recorded_at' => gmdate('c'),
        ];

        $fixture = $this->resolveFixture($request);
        $secretFields = $request->meta['credentialsEnrichment']['secretKeys'] ?? [];
        $payload = $this->redactor->redact(
            $payload,
            $fixture,
            is_array($secretFields) ? array_values(array_filter($secretFields, 'is_string')) : [],
        );

        $payload = $request->destination?->redactReferences($payload) ?? $payload;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $this->publish($payload['request']['class'], $json);
    }

    private function resolveFixture(PreparedRequest $request): ?Fixture
    {
        $class = $request->meta['requestClass'] ?? null;
        if (!is_string($class)) {
            return null;
        }

        return $this->fixtures[$class] ?? null;
    }

    /** Публикует целую fixture без перезаписи файла другого recorder. */
    private function publish(?string $requestClass, string $json): void
    {
        $temporary = null;
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            if (!is_dir($this->path)) {
                try {
                    if (!mkdir($this->path, 0777, true)) {
                        throw new RuntimeException('Не удалось создать каталог fixtures');
                    }
                } catch (Throwable $exception) {
                    if (!is_dir($this->path)) {
                        throw $exception;
                    }
                }
            }
            $temporary = tempnam($this->path, '.recording-');
            if ($temporary === false || realpath(dirname($temporary)) !== realpath($this->path)) {
                throw new RuntimeException('Не удалось создать временную fixture в целевом каталоге');
            }
            if (file_put_contents($temporary, $json) !== strlen($json)) {
                throw new RuntimeException('Fixture записана не полностью');
            }
            $base = $requestClass !== null ? (new ReflectionClass($requestClass))->getShortName() : 'request';
            for ($index = 1;; $index++) {
                $candidate = $this->path . '/' . $base . '_' . $index . '.json';
                try {
                    // link атомарно отказывает, если другой процесс уже занял имя.
                    if (!link($temporary, $candidate)) {
                        throw new RuntimeException('Не удалось опубликовать fixture');
                    }
                    break;
                } catch (Throwable $exception) {
                    if (!file_exists($candidate) && !is_link($candidate)) {
                        throw $exception;
                    }
                }
            }
            if (!unlink($temporary)) {
                throw new RuntimeException('Не удалось удалить временную fixture');
            }
            $temporary = null;
        } finally {
            restore_error_handler();
            if (is_string($temporary) && is_file($temporary)) {
                // Удаляется только собственный временный файл, опубликованные файлы не затрагиваются.
                @unlink($temporary);
            }
        }
    }

    private function normalizeBody(?string $body, ?string $contentType): array|string|null
    {
        if ($body === null) {
            return null;
        }

        $isJson = $contentType !== null && str_contains((string) $contentType, 'json');
        if ($isJson) {
            $decoded = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $decoded = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
        return is_array($decoded) ? $decoded : $body;
    }
}
