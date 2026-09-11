<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderC;

use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCReportStatus;
use Brahmic\ApiSutra\Tests\Stubs\ProviderC\Enums\ProviderCSystemStatus;

final class ProviderCStatusMap
{
    public static function mapSystem(ProviderCSystemStatus $status): ResultStatus
    {
        return $status === ProviderCSystemStatus::Ok
            ? ResultStatus::SUCCESS
            : ResultStatus::FAILED;
    }

    public static function mapReport(ProviderCReportStatus $status, ?int $waitTime): ResultStatus
    {
        if ($status === ProviderCReportStatus::Ready) {
            return ResultStatus::SUCCESS;
        }

        if ($status === ProviderCReportStatus::Waiting && $waitTime !== null) {
            return ResultStatus::PARTIAL;
        }

        return ResultStatus::FAILED;
    }
}
