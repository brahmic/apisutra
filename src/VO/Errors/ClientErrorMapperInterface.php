<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\VO\Errors;

use Brahmic\ApiSutra\Collections\ErrorCollection;

/**
 * Контракт стратегии маппинга ошибок.
 */
interface ClientErrorMapperInterface
{
    public function map(RequestError $error): ClientError;

    public function status(ErrorCollection $errors): int;
}
