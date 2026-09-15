<?php

declare(strict_types=1);

namespace Example\Continuation;

use Brahmic\ApiSutra\Result\ContinuationTokenExtractorInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;

final readonly class TokenExtractor implements ContinuationTokenExtractorInterface
{
    public function extract(ExecutionResult $result): ?string
    {
        $data = $result->response?->json();
        $token = is_array($data) ? ($data['operationToken'] ?? null) : null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}
