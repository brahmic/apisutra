<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderB\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Casts\EnumCast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\ProviderB\Enums\ProviderBErrorCode;
use Brahmic\ApiSutra\Tests\Stubs\ProviderB\Enums\ProviderBStatus;

final readonly class ProviderBAsyncResponseDto extends AbstractDto
{
    /**
     * Данные ответа.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(
        #[From('status')]
        #[Cast(EnumCast::class, ProviderBStatus::class)]
        public ProviderBStatus $status,
        #[From('error_code')]
        #[Cast(EnumCast::class, ProviderBErrorCode::class)]
        public ?ProviderBErrorCode $errorCode = null,
        #[From('operation_id')]
        public ?string $operationId = null,
        #[From('data')]
        ?array $data = null,
    ) {
        $this->data = $data ?? [];
    }

    /**
     * Данные ответа.
     *
     * @var array<string, mixed>
     */
    public array $data;
}
