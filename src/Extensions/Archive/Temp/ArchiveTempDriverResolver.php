<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Extensions\Archive\Temp;

use Brahmic\ApiSutra\Config\ArchiveConfig;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Spatie\TemporaryDirectory\TemporaryDirectory;

final readonly class ArchiveTempDriverResolver
{
    public function resolve(
        ?ArchiveConfig $config = null,
        ?TempDirectoryProviderInterface $provider = null,
    ): TempDirectoryProviderInterface {
        if ($provider instanceof TempDirectoryProviderInterface) {
            return $provider;
        }

        if ($config?->tempProvider instanceof TempDirectoryProviderInterface) {
            return $config->tempProvider;
        }

        $driver = strtolower($config->driver ?? 'native');
        if ($driver === '') {
            $driver = 'native';
        }
        $baseDir = $config?->tempDir;

        return match ($driver) {
            'native' => new NativeTempDirectoryProvider($baseDir),
            'spatie' => $this->resolveSpatie($baseDir),
            'auto' => $this->resolveAuto($baseDir),
            default => throw new ConfigurationException('Неизвестный драйвер временной директории архива'),
        };
    }

    private function resolveAuto(?string $baseDir): TempDirectoryProviderInterface
    {
        if (class_exists(TemporaryDirectory::class)) {
            return new SpatieTempDirectoryProvider($baseDir);
        }

        return new NativeTempDirectoryProvider($baseDir);
    }

    private function resolveSpatie(?string $baseDir): TempDirectoryProviderInterface
    {
        if (!class_exists(TemporaryDirectory::class)) {
            throw new ConfigurationException('Пакет spatie/temporary-directory не установлен');
        }

        return new SpatieTempDirectoryProvider($baseDir);
    }
}
