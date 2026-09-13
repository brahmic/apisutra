<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\RateLimiting;

use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

final readonly class RateLimitQuota
{
    public function __construct(
        public string $key,
        public int $limit,
        public int $periodMs,
    ) {
        if ($key === '' || $limit < 1 || $periodMs < 1 || $periodMs > intdiv(PHP_INT_MAX, 1000)) {
            throw new ConfigurationException(
                'Некорректная квота: требуются ключ, положительный limit и представимый periodMs',
            );
        }
    }

    /**
     * @param list<self> $quotas
     * @return list<self>
     */
    public static function normalize(array $quotas): array
    {
        $unique = [];
        foreach ($quotas as $quota) {
            $previous = $unique[$quota->key] ?? null;
            if (
                $previous !== null
                && ($previous->limit !== $quota->limit || $previous->periodMs !== $quota->periodMs)
            ) {
                throw new ConfigurationException('Противоречивые определения одной квоты');
            }
            $unique[$quota->key] = $quota;
        }
        return array_values($unique);
    }
}
