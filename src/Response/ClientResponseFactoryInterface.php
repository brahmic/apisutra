<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Response;

use Brahmic\ApiSutra\Result\ResolvedResultInterface;

/**
 * Контракт фабрики клиентского ответа.
 */
interface ClientResponseFactoryInterface
{
    public function make(ResolvedResultInterface $result): ClientResponse;
}
