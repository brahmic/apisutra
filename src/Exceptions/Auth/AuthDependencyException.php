<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Auth;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\Result\ExecutionResult;

/** Внутренняя доставка результата refresh до первого основного HTTP. */
final class AuthDependencyException extends SdkException
{
    public function __construct(public readonly ExecutionResult $dependencyResult)
    {
        parent::__construct('Не удалось обновить авторизацию');
    }
}
