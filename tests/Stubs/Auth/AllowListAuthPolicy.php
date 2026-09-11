<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Auth;

use Brahmic\ApiSutra\Contracts\Interfaces\Auth\AuthPolicyInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;

final readonly class AllowListAuthPolicy implements AuthPolicyInterface
{
    /**
     * @param array<class-string<RequestInterface>> $allowed
     */
    public function __construct(
        private array $allowed,
    ) {}

    public function allowedRequests(): array
    {
        return $this->allowed;
    }
}
