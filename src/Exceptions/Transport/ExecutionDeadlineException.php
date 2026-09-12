<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Transport;

use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Throwable;

final class ExecutionDeadlineException extends TimeoutException
{
    public function __construct(
        public readonly string $stage,
        ?Throwable $previous = null,
        public readonly ?ProviderResponse $response = null,
        public readonly ?int $bytesWritten = null,
        public readonly bool $partial = false,
    )
    {
        parent::__construct('Исчерпан общий бюджет выполнения: ' . $stage, 0, $previous);
    }
}
