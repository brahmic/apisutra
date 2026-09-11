<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Auth;

/**
 * Переопределение поведения auth на уровне запроса.
 */
enum AuthOverride
{
    case Enable;
    case Disable;
    case ForceEnable;
}
