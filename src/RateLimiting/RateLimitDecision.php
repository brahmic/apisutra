<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\RateLimiting;

use InvalidArgumentException;

final readonly class RateLimitDecision
{
    /** @var list<string> */
    public array $blockedIds;

    /** @param array<array-key, mixed> $blockedIds Непроверенный ответ внешнего backend. */
    public function __construct(
        public bool $granted,
        array $blockedIds = [],
        public int $retryAfterMs = 0,
    ) {
        if ($granted ? ($blockedIds !== [] || $retryAfterMs !== 0) : ($blockedIds === [] || $retryAfterMs < 1)) {
            throw new InvalidArgumentException('Некорректный результат учёта квот');
        }
        $ids = [];
        foreach ($blockedIds as $id) {
            if (!is_string($id) || $id === '') {
                throw new InvalidArgumentException('Некорректный идентификатор блокирующей квоты');
            }
            $ids[] = $id;
        }
        if (!array_is_list($blockedIds) || count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException('Некорректный список блокирующих квот');
        }
        $this->blockedIds = $ids;
    }
}
