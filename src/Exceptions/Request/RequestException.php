<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Request;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;

class RequestException extends SdkException
{
    public function __construct(
        string $message,
        public readonly ProviderResponse $response,
        int $code = 0,
        ?SdkException $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
