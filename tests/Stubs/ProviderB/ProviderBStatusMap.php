<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderB;

use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Tests\Stubs\ProviderB\Enums\ProviderBErrorCode;
use Brahmic\ApiSutra\Tests\Stubs\ProviderB\Enums\ProviderBStatus;

final class ProviderBStatusMap
{
    public static function mapStatus(ProviderBStatus $status): ResultStatus
    {
        return match ($status) {
            ProviderBStatus::Ready => ResultStatus::SUCCESS,
            ProviderBStatus::Accepted,
            ProviderBStatus::InProgress,
            ProviderBStatus::PaymentRequired,
            ProviderBStatus::Suspended => ResultStatus::PARTIAL,
            ProviderBStatus::Failed,
            ProviderBStatus::NotFound,
            ProviderBStatus::ValidationError,
            ProviderBStatus::Rejected,
            ProviderBStatus::Timeout,
            ProviderBStatus::Canceled => ResultStatus::FAILED,
        };
    }

    public static function mapError(ProviderBErrorCode $code): ResultStatus
    {
        return match ($code) {
            ProviderBErrorCode::InvalidInput => ResultStatus::FAILED,
            ProviderBErrorCode::NotFound => ResultStatus::FAILED,
            ProviderBErrorCode::ProviderError => ResultStatus::FAILED,
            ProviderBErrorCode::LimitExceeded => ResultStatus::FAILED,
        };
    }
}
