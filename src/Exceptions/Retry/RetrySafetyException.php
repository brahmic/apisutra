<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Retry;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Throwable;

/** Ошибка пользовательской проверки не является причиной повторять её или HTTP. */
final class RetrySafetyException extends SdkException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('Ошибка проверки безопасности повтора', 0, $previous);
    }
}
