<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderC\Dto;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Casts\EnumCast;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCReportStatus;

final readonly class ProviderCReportResponseDto extends AbstractDto
{
    /**
     * @param array<string, mixed> $response
     */
    public function __construct(
        #[From('status')]
        #[Cast(EnumCast::class, ProviderCReportStatus::class)]
        public ProviderCReportStatus $status,
        #[From('waitTime')]
        public ?int $waitTime = null,
        #[From('response')]
        ?array $response = null,
    ) {
        $this->response = $response ?? [];
    }

    /**
     * Данные отчёта.
     *
     * @var array<string, mixed>
     */
    public array $response;
}
