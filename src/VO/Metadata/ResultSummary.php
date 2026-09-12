<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\VO\Metadata;

use Brahmic\ApiSutra\Enums\Result\ResultStatus;

/**
 * Сводка по результатам выполнения.
 */
readonly class ResultSummary
{
    public function __construct(
        public int $total,
        public int $successful,
        public int $failed,
        public int $partial,
        public ResultStatus $status,
    ) {
    }

    public function isAllSuccess(): bool
    {
        return $this->total > 0 && $this->successful === $this->total;
    }

    public function isAllFailed(): bool
    {
        return $this->total > 0 && $this->failed === $this->total;
    }

    public function isAllPartial(): bool
    {
        return $this->total > 0 && $this->partial === $this->total;
    }
}
