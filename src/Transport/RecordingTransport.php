<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Transport;

use Brahmic\ApiSutra\Diagnostics\RedactionPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Testing\Fixture;
use Brahmic\ApiSutra\Testing\FixtureRedactor;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use ReflectionClass;
use Throwable;

final class RecordingTransport implements TransportInterface
{
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
        $response = $this->transport->send($request);
        $this->record($request, $response);
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
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }

        $payload = [
            'request' => [
                'class' => $request->meta['requestClass'] ?? null,
                'method' => $request->method->value,
                'url' => $request->url,
                'headers' => $request->headers,
                'body' => $this->normalizeBody($request->body, $request->headers['Content-Type'] ?? null),
            ],
            'response' => [
                'status' => $response->status,
                'headers' => $response->headers,
                'body' => $this->normalizeBody($response->body, $response->header('Content-Type')),
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

        $file = $this->resolveFilename($payload['request']['class']);
        file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function resolveFixture(PreparedRequest $request): ?Fixture
    {
        $class = $request->meta['requestClass'] ?? null;
        if (!is_string($class)) {
            return null;
        }

        return $this->fixtures[$class] ?? null;
    }

    private function resolveFilename(?string $requestClass): string
    {
        $base = $requestClass !== null ? (new ReflectionClass($requestClass))->getShortName() : 'request';
        $index = 1;
        $candidate = $this->path . '/' . $base . '_' . $index . '.json';
        while (file_exists($candidate)) {
            $index++;
            $candidate = $this->path . '/' . $base . '_' . $index . '.json';
        }

        return $candidate;
    }

    private function normalizeBody(?string $body, ?string $contentType): array|string|null
    {
        if ($body === null) {
            return null;
        }

        $isJson = $contentType !== null && str_contains((string) $contentType, 'json');
        if ($isJson) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : $body;
    }
}
