<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\ProviderA;

use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Tests\Stubs\ProviderA\Enums\ProviderAErrorCode;
use Brahmic\ApiSutra\Tests\Stubs\ProviderA\Enums\ProviderAResultCode;

final class ProviderAStatusMap
{
    public static function mapResult(ProviderAResultCode $code): ResultStatus
    {
        return match ($code) {
            ProviderAResultCode::Ok => ResultStatus::SUCCESS,
            ProviderAResultCode::Warning => ResultStatus::PARTIAL,
            ProviderAResultCode::NoData => ResultStatus::SUCCESS,
        };
    }

    public static function mapError(ProviderAErrorCode $code): ResultStatus
    {
        return match ($code) {
            ProviderAErrorCode::InvalidInput => ResultStatus::FAILED,
            ProviderAErrorCode::NotFound => ResultStatus::FAILED,
            ProviderAErrorCode::ProviderError => ResultStatus::FAILED,
            ProviderAErrorCode::Timeout => ResultStatus::FAILED,
        };
    }
}
