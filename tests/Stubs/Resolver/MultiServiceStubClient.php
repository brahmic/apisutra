<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Resolver;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;

final readonly class MultiServiceStubClient implements MultiServiceClientInterface
{
    /**
     * @param array<int, ClientInterface> $services
     */
    public function __construct(
        private array $services,
    ) {}

    /**
     * @return array<int, ClientInterface>
     */
    public function services(): array
    {
        return $this->services;
    }
}
