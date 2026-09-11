<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Cache;

use Psr\SimpleCache\CacheInterface;

interface CacheAwareInterface
{
    /**
     * Установить кеш для хранения токенов
     */
    public function setCache(CacheInterface $cache): void;

    /**
     * Ключ кеша для токена
     */
    public function getCacheKey(): string;
}
