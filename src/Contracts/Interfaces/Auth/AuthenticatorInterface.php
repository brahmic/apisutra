<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Auth;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

interface AuthenticatorInterface
{
    /**
     * Добавить auth данные к запросу
     */
    public function authenticate(PreparedRequest $request): PreparedRequest;

    /**
     * Нужен ли refresh токена (до запроса)
     */
    public function shouldRefresh(): bool;

    /**
     * Запрос на refresh токена
     */
    public function getRefreshRequest(): ?RequestInterface;

    /**
     * Обработка ответа refresh
     */
    public function processTokenResponse(ResponseDtoInterface $response): void;
}
