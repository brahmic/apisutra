<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Transport;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Exceptions\Testing\MissingFixtureException;
use Brahmic\ApiSutra\Exceptions\Testing\UnmockedRequestException;
use Brahmic\ApiSutra\Testing\Fixture;
use Brahmic\ApiSutra\Testing\MockConfig;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Testing\MockSequence;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;

final class MockTransport implements TransportInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $responses = [];

    /**
     * @var array<int, PreparedRequest>
     */
    private array $recorded = [];

    private bool $preventStray = false;

    public function fake(array $responses): void
    {
        $this->responses = $responses;
    }

    public function loadFixtures(string $path): void
    {
        $files = glob(rtrim($path, '/') . '/*.json') ?: [];
        $grouped = [];

        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }

            $class = $data['request']['class'] ?? null;
            if (!is_string($class)) {
                continue;
            }

            $response = $data['response'] ?? [];
            $body = $response['body'] ?? [];
            $status = (int) ($response['status'] ?? 200);
            $headers = $this->normalizeFixtureHeaders($response['headers'] ?? []);

            $grouped[$class] ??= [];
            $grouped[$class][] = new MockResponse($body, $status, $headers);
        }

        foreach ($grouped as $class => $responses) {
            $this->responses[$class] = count($responses) > 1
                ? new MockSequence($responses)
                : $responses[0];
        }
    }

    public function preventStrayRequests(): void
    {
        $this->preventStray = true;
    }

    #[\Override]
    public function send(PreparedRequest $request): ProviderResponse
    {
        $this->recorded[] = $request;

        $response = $this->findResponse($request);
        if ($response === null && $this->preventStray) {
            throw new UnmockedRequestException('Незамоканный запрос');
        }

        return $response ?? MockResponse::notFound()->toProviderResponse($request);
    }

    #[\Override]
    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        $promise = new Promise();

        try {
            $promise->resolve($this->send($request));
        } catch (\Throwable $exception) {
            $promise->reject($exception);
        }

        return $promise;
    }

    /**
     * @return array<int, PreparedRequest>
     */
    public function getRecorded(): array
    {
        return $this->recorded;
    }

    public function assertSent(string $requestClass, ?callable $callback = null, ?int $times = null): void
    {
        $count = 0;
        foreach ($this->recorded as $recorded) {
            $class = $recorded->meta['requestClass'] ?? null;
            if ($class !== $requestClass) {
                continue;
            }

            $instance = $recorded->meta['requestInstance'] ?? $recorded;
            if ($callback === null || $callback($instance)) {
                $count++;
            }
        }

        if ($times !== null) {
            if ($count !== $times) {
                throw new \RuntimeException("Запрос {$requestClass} был отправлен {$count} раз(а)");
            }
            return;
        }

        if ($count === 0) {
            throw new \RuntimeException("Запрос {$requestClass} не был отправлен");
        }
    }

    public function assertNotSent(string $requestClass): void
    {
        foreach ($this->recorded as $recorded) {
            $class = $recorded->meta['requestClass'] ?? null;
            if ($class === $requestClass) {
                throw new \RuntimeException("Запрос {$requestClass} был отправлен");
            }
        }
    }

    public function assertNothingSent(): void
    {
        if ($this->recorded !== []) {
            throw new \RuntimeException('Были отправлены запросы');
        }
    }

    private function findResponse(PreparedRequest $request): ?ProviderResponse
    {
        $requestClass = $request->meta['requestClass'] ?? null;
        if ($requestClass !== null && array_key_exists($requestClass, $this->responses)) {
            return $this->resolveResponse($this->responses[$requestClass], $request);
        }

        foreach ($this->responses as $pattern => $response) {
            if ($pattern === '*' || $this->matches($request->url, (string) $pattern)) {
                return $this->resolveResponse($response, $request);
            }
        }

        $fixturePath = MockConfig::getFixturePath();
        if ($fixturePath !== null && MockConfig::shouldThrowOnMissingFixtures()) {
            throw new MissingFixtureException("Фикстура для запроса {$requestClass} не найдена");
        }

        return null;
    }

    private function resolveResponse(mixed $response, PreparedRequest $request): ?ProviderResponse
    {
        if ($response instanceof ProviderResponse) {
            return $response;
        }

        if ($response instanceof MockSequence) {
            return $response->next()->toProviderResponse($request);
        }

        if ($response instanceof MockResponse) {
            return $response->toProviderResponse($request);
        }

        if ($response instanceof Fixture) {
            return $this->resolveFixture($response, $request);
        }

        if (is_callable($response)) {
            $result = $response($request->meta['requestInstance'] ?? $request);
            return $this->resolveResponse($result, $request);
        }

        if (is_array($response) || is_string($response)) {
            return MockResponse::make($response)->toProviderResponse($request);
        }

        return null;
    }

    private function resolveFixture(Fixture $fixture, PreparedRequest $request): ?ProviderResponse
    {
        $path = MockConfig::getFixturePath();
        if ($path === null) {
            throw new MissingFixtureException('Не указан путь к фикстурам');
        }

        $file = rtrim($path, '/') . '/' . $fixture->name();
        if (!str_ends_with($file, '.json')) {
            $file .= '.json';
        }

        if (!file_exists($file)) {
            throw new MissingFixtureException("Фикстура {$file} не найдена");
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new MissingFixtureException("Фикстура {$file} повреждена");
        }

        $response = $data['response'] ?? [];
        $body = $response['body'] ?? [];
        $status = (int) ($response['status'] ?? 200);
        $headers = $this->normalizeFixtureHeaders($response['headers'] ?? []);

        return (new MockResponse($body, $status, $headers))->toProviderResponse($request);
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, string>
     */
    private function normalizeFixtureHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                $result[$name] = (string) ($value[0] ?? '');
                continue;
            }
            $result[$name] = (string) $value;
        }

        return $result;
    }

    private function matches(string $url, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
        return (bool) preg_match('/' . $pattern . '/i', $url);
    }
}
