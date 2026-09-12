<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\RateLimiting;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Throwable;

final class RateLimitBackendException extends SdkException
{
    public function __construct(
        ?Throwable $previous = null,
        public readonly ?ProviderResponse $lastResponse = null,
    ) {
        parent::__construct('Ошибка хранилища rate-limit', previous: $previous);
    }
}
