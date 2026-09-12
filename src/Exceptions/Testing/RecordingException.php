<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Testing;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Throwable;

final class RecordingException extends SdkException
{
    public function __construct(public readonly ProviderResponse $response, Throwable $previous)
    {
        parent::__construct('Не удалось записать fixture выполненного HTTP-запроса', previous: $previous);
    }
}
