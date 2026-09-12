<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Request;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;

class RateLimitException extends ClientException
{
    public function __construct(
        string $message,
        ?ProviderResponse $response,
        public readonly ?int $retryAfter = null,
        int $code = 0,
        ?SdkException $previous = null,
        public readonly ?ProviderResponse $lastResponse = null,
    ) {
        parent::__construct($message, $response, $code, $previous);
    }
}
