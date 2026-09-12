<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\RateLimitProbe;

use Brahmic\ApiSutra\Tests\Support\ArrayCache;
use DateInterval;
use Override;
use RuntimeException;

/** Одноключевой стенд: отдельные get/set защищены, вся операция acquire — нет. */
final class RaceStore extends ArrayCache
{
    public function __construct(private readonly string $directory) {}

    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        $handle = fopen($this->directory . '/state.json', 'c+');
        flock($handle, LOCK_SH);
        $raw = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        $value = $raw === '' ? $default : json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        touch($this->directory . '/ready-' . getmypid());
        $deadline = hrtime(true) + 3_000_000_000;
        while (count(glob($this->directory . '/ready-*')) < 2) {
            if (hrtime(true) >= $deadline) {
                throw new RuntimeException('Второй процесс не достиг барьера');
            }
            usleep(1000);
        }
        return $value;
    }

    #[Override]
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $handle = fopen($this->directory . '/state.json', 'c+');
        flock($handle, LOCK_EX);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($value, JSON_THROW_ON_ERROR));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        return true;
    }
}
