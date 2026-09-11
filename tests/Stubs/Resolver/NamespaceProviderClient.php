<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Resolver;

use Brahmic\ApiSutra\Contracts\Interfaces\Resolver\RequestNamespaceProviderInterface;

final class NamespaceProviderClient extends BaseStubClient implements RequestNamespaceProviderInterface
{
    /**
     * @return array<int, string>
     */
    public function requestNamespaces(): array
    {
        return [
            'Brahmic\\ApiSutra\\Tests\\Stubs\\Requests',
        ];
    }
}
