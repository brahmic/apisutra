<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\Result\ContinuationTokenExtractorInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Result\ResolvedResult;
use Brahmic\ApiSutra\VO\Errors\ClientErrorFactory;
use Brahmic\ApiSutra\VO\Errors\DefaultClientErrorMapper;

describe('ResolvedResult continuation token', function () {
    it('возвращает null без extractor', function () {
        $execution = new ExecutionResult(
            data: ['ok' => true],
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
        );
        $resolved = new ResolvedResult(
            result: $execution,
            errorFactory: new ClientErrorFactory(new DefaultClientErrorMapper()),
        );

        expect($resolved->continuationToken())->toBeNull();
    });

    it('возвращает токен из extractor', function () {
        $execution = new ExecutionResult(
            data: ['ok' => true],
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
        );
        $resolved = new ResolvedResult(
            result: $execution,
            errorFactory: new ClientErrorFactory(new DefaultClientErrorMapper()),
            continuationTokenExtractor: new class ('op-123') implements ContinuationTokenExtractorInterface
            {
                public function __construct(
                    private readonly ?string $token,
                ) {}

                #[\Override]
                public function extract(ExecutionResult $result): ?string
                {
                    return $this->token;
                }
            },
        );

        expect($resolved->continuationToken())->toBe('op-123')
            ->and($resolved->continuationTokenOrFail())->toBe('op-123');
    });

    it('continuationTokenOrFail бросает исключение при отсутствии токена', function () {
        $execution = new ExecutionResult(
            data: ['ok' => true],
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
        );
        $resolved = new ResolvedResult(
            result: $execution,
            errorFactory: new ClientErrorFactory(new DefaultClientErrorMapper()),
            continuationTokenExtractor: new class (null) implements ContinuationTokenExtractorInterface
            {
                public function __construct(
                    private readonly ?string $token,
                ) {}

                #[\Override]
                public function extract(ExecutionResult $result): ?string
                {
                    return $this->token;
                }
            },
        );

        expect(fn () => $resolved->continuationTokenOrFail())
            ->toThrow(SdkException::class, 'Continuation token отсутствует в результате');
    });

    it('кеширует extractor continuation token', function () {
        $execution = new ExecutionResult(
            data: ['ok' => true],
            status: ResultStatus::SUCCESS,
            errors: new ErrorCollection([]),
        );
        $extractor = new class implements ContinuationTokenExtractorInterface
        {
            public int $calls = 0;

            #[\Override]
            public function extract(ExecutionResult $result): ?string
            {
                $this->calls++;

                return 'tok-123';
            }
        };
        $resolved = new ResolvedResult(
            result: $execution,
            errorFactory: new ClientErrorFactory(new DefaultClientErrorMapper()),
            continuationTokenExtractor: $extractor,
        );

        $first = $resolved->continuationToken();
        $second = $resolved->continuationToken();
        $third = $resolved->continuationTokenOrFail();

        expect($first)->toBe('tok-123')
            ->and($second)->toBe('tok-123')
            ->and($third)->toBe('tok-123')
            ->and($extractor->calls)->toBe(1);
    });
});
