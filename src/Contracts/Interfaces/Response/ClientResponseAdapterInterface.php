<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Response;

use Brahmic\ApiSutra\Response\ClientResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контракт адаптера клиентского ответа для Laravel.
 */
interface ClientResponseAdapterInterface
{
    public function toResponse(ClientResponse $response): Response;
}
