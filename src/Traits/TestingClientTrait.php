<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Traits;

use Brahmic\ApiSutra\Testing\Fixture;
use Brahmic\ApiSutra\Testing\MockConfig;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\Transport\RecordingTransport;

/**
 * Тестовые методы клиента.
 */
trait TestingClientTrait
{
    public function fake(array $responses): static
    {
        $transport = $this->ensureMockTransport();
        $transport->fake($responses);
        $this->rebuildPipeline();

        return $this;
    }

    public function preventStrayRequests(): void
    {
        $this->mockTransport()?->preventStrayRequests();
    }

    /**
     * Записать ответы в фикстуры
     * @param array<string, Fixture> $fixtures
     */
    public function record(string $path, array $fixtures = []): static
    {
        $this->transport = new RecordingTransport($this->transport, $path, $fixtures, $this->config->redaction);
        MockConfig::setFixturePath($path);
        $this->rebuildPipeline();

        return $this;
    }

    /**
     * Воспроизвести ответы из фикстур
     */
    public function playback(string $path): static
    {
        $transport = new MockTransport();
        $transport->loadFixtures($path);
        $this->transport = $transport;
        MockConfig::setFixturePath($path);
        $this->rebuildPipeline();

        return $this;
    }

    public function assertSent(string $requestClass, ?callable $callback = null, ?int $times = null): void
    {
        $this->mockTransport()?->assertSent($requestClass, $callback, $times);
    }

    public function assertNotSent(string $requestClass): void
    {
        $this->mockTransport()?->assertNotSent($requestClass);
    }

    public function assertNothingSent(): void
    {
        $this->mockTransport()?->assertNothingSent();
    }

    private function ensureMockTransport(): MockTransport
    {
        if (!$this->transport instanceof MockTransport) {
            $this->transport = new MockTransport();
        }

        return $this->transport;
    }

    private function mockTransport(): ?MockTransport
    {
        return $this->transport instanceof MockTransport ? $this->transport : null;
    }
}
