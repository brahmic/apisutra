<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Flow;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Validation\CustomValidatableRequestInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\VO\Errors\ValidationError;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Brahmic\ApiSutra\VO\Validation\Validator;

/**
 * Валидация запроса: attribute #[Validate] + опциональная custom preflight.
 *
 * @see CustomValidatableRequestInterface
 * @see docs/guides/validation.md
 */
final readonly class PipelineValidator
{
    public function __construct(
        private ExecutionResultBuilder $resultBuilder,
    ) {
    }

    public function validate(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
    ): ?ExecutionResult {
        $errors = [];

        $validation = Validator::checkForClient($request, $context->config);
        if ($validation->failed()) {
            $errors = array_merge($errors, $validation->errors());
        }

        if ($request instanceof CustomValidatableRequestInterface) {
            $custom = $request->validateCustom();
            $errors = array_merge($errors, $custom);
        }

        if ($errors === []) {
            return null;
        }

        return $this->resultBuilder->buildValidationFailure(
            request: $request,
            context: $context,
            audit: $audit,
            startTime: $startTime,
            validationErrors: $errors,
        );
    }
}
