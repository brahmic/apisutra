<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Extensions\Archive\Temp;

use Brahmic\ApiSutra\Config\ArchiveConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Throwable;

final readonly class ArchiveTempFileManager
{
    private TempDirectoryProviderInterface $provider;
    private ?int $maxSize;

    public function __construct(?ClientConfig $config = null, ?TempDirectoryProviderInterface $provider = null)
    {
        $archiveConfig = $config ? ArchiveConfig::fromClientConfig($config) : null;
        $resolver = new ArchiveTempDriverResolver();
        $this->provider = $resolver->resolve($archiveConfig, $provider ?? $archiveConfig?->tempProvider);
        $this->maxSize = $archiveConfig?->maxSize;
    }

    public function createFromContent(string $content, ?string $suffix = null): string
    {
        $this->assertMaxSize($content);

        $path = $this->provider->createTempFile($suffix);
        try {
            $written = file_put_contents($path, $content);
            if ($written === false || $written !== strlen($content)) {
                throw new ConfigurationException('Не удалось записать архив во временный файл');
            }
        } catch (Throwable $exception) {
            $this->cleanupOrFail($path);
            if ($exception instanceof ConfigurationException) {
                throw $exception;
            }

            throw new ConfigurationException('Не удалось подготовить временный файл архива', 0, $exception);
        }

        return $path;
    }

    public function cleanupOrFail(string $path): void
    {
        $this->provider->cleanup($path);
    }

    public function cleanupQuietly(string $path): void
    {
        try {
            $this->provider->cleanup($path);
        } catch (Throwable) {
        }
    }

    private function assertMaxSize(string $content): void
    {
        if ($this->maxSize === null || $this->maxSize <= 0) {
            return;
        }

        if (strlen($content) <= $this->maxSize) {
            return;
        }

        throw new ConfigurationException('Размер архива превышает допустимый лимит');
    }
}
