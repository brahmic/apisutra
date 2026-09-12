<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Flow;

use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;

final readonly class RequestContractFieldValue
{
    public function __construct(
        public string $path,
        public ValueState $state,
        public mixed $value,
    ) {
    }

    public function isFilled(): bool
    {
        return $this->state === ValueState::Present;
    }
}
