<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Auth;

use Brahmic\ApiSutra\Exceptions\Transport\TimeoutException;

final class AuthRefreshLockTimeoutException extends TimeoutException
{
    public function __construct()
    {
        parent::__construct('Истекло время ожидания обновления токена');
    }
}
