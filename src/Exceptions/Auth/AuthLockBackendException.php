<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Auth;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Throwable;

final class AuthLockBackendException extends SdkException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('Ошибка backend auth-блокировки', previous: $previous);
    }
}
