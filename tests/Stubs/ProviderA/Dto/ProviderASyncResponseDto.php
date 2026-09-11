<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderA\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Casts\EnumCast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\ProviderA\Enums\ProviderAErrorCode;
use Brahmic\ApiSutra\Tests\Stubs\ProviderA\Enums\ProviderAResultCode;

final readonly class ProviderASyncResponseDto extends AbstractDto
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        #[From('result_code')]
        #[Cast(EnumCast::class, ProviderAResultCode::class)]
        public ProviderAResultCode $resultCode,
        #[From('error_code')]
        #[Cast(EnumCast::class, ProviderAErrorCode::class)]
        public ?ProviderAErrorCode $errorCode = null,
        #[From('operation_token')]
        public ?string $operationToken = null,
        #[From('data')]
        public array $data = [],
    ) {}
}
