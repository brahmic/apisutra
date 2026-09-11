<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Support\Result;

use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;

final readonly class ProviderEnvelopeMeta implements ResultMeta
{
    public function __construct(
        public ?int $resultCode,
        public ?string $resultMessage,
        public ?string $operationToken,
    ) {}
}
