<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Auth;

use Brahmic\ApiSutra\Exceptions\Request\UnauthorizedException;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Throwable;

/** Основной 401 и диагностика восстановления доступны отдельно от safe context. */
final class AuthRefreshFailedException extends UnauthorizedException
{
    public function __construct(
        ProviderResponse $response,
        public readonly ?ExecutionResult $dependencyResult,
        public readonly Throwable $recoveryException,
    ) {
        parent::__construct('Не удалось восстановить авторизацию после 401', $response);
    }
}
