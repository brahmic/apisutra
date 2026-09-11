<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Auth;

use Psr\SimpleCache\CacheInterface;

final class AuthRefreshLock
{
    /**
     * @var array<string, array{token: string, expires_at: int}> Локальные блокировки
     */
    private array $localLocks = [];

    public function __construct(
        private readonly ?CacheInterface $cache = null,
    ) {}

    public function acquire(string $key, int $ttlSeconds): ?string
    {
        $token = bin2hex(random_bytes(16));

        if ($this->cache !== null && method_exists($this->cache, 'add')) {
            $added = $this->cache->add($key, $token, $ttlSeconds);
            return $added ? $token : null;
        }

        return $this->acquireLocal($key, $token, $ttlSeconds);
    }

    public function release(string $key, string $token): void
    {
        if ($this->cache !== null) {
            $current = $this->cache->get($key);
            if (is_string($current) && hash_equals($current, $token)) {
                $this->cache->delete($key);
            }
            return;
        }

        $current = $this->localLocks[$key] ?? null;
        if (is_array($current) && isset($current['token']) && is_string($current['token']) && hash_equals($current['token'], $token)) {
            unset($this->localLocks[$key]);
        }
    }

    private function acquireLocal(string $key, string $token, int $ttlSeconds): ?string
    {
        $lock = $this->localLocks[$key] ?? null;
        if (is_array($lock)) {
            $expiresAt = $lock['expires_at'] ?? null;
            if (is_int($expiresAt) && $expiresAt > time()) {
                return null;
            }
        }

        $this->localLocks[$key] = [
            'token' => $token,
            'expires_at' => time() + $ttlSeconds,
        ];

        return $token;
    }
}
