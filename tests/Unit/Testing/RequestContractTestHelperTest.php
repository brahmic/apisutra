<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Testing\RequestContractTestHelper;
use Brahmic\ApiSutra\VO\Errors\RequestError;

describe('RequestContractTestHelper', function () {
    it('определяет RequestContractViolation и возвращает контекст', function () {
        $result = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([
                new RequestError(
                    code: ErrorCode::RequestContractViolation,
                    message: 'x',
                    context: [
                        'contract' => 'signature_payload',
                        'violations' => [
                            ['code' => 'none_selected'],
                            ['code' => 'required_common_missing'],
                        ],
                    ],
                ),
            ]),
        );

        expect(RequestContractTestHelper::isRequestContractViolation($result))->toBeTrue()
            ->and(RequestContractTestHelper::context($result)['contract'] ?? null)->toBe('signature_payload')
            ->and(RequestContractTestHelper::violationCodes($result))->toBe([
                'none_selected',
                'required_common_missing',
            ])
            ->and(RequestContractTestHelper::hasViolationCode($result, 'none_selected'))->toBeTrue();
    });

    it('возвращает пустые значения для других кодов ошибок', function () {
        $result = new ExecutionResult(
            data: null,
            status: ResultStatus::FAILED,
            errors: new ErrorCollection([
                new RequestError(
                    code: ErrorCode::ValidationFailed,
                    message: 'validation',
                    context: ['violations' => [['code' => 'x']]],
                ),
            ]),
        );

        expect(RequestContractTestHelper::isRequestContractViolation($result))->toBeFalse()
            ->and(RequestContractTestHelper::context($result))->toBeNull()
            ->and(RequestContractTestHelper::violationCodes($result))->toBe([])
            ->and(RequestContractTestHelper::hasViolationCode($result, 'x'))->toBeFalse();
    });
});
