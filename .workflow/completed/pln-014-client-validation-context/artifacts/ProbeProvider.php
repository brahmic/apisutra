<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\ValidationProbe;

use Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Override;

final class ProbeProvider implements ContainerProviderInterface
{
    public int $factoryCalls = 0;

    public function __construct(private readonly ?object $factory) {}

    #[Override]
    public function bound(string $id): bool { return false; }
    #[Override]
    public function make(string $id): ?object { return null; }
    #[Override]
    public function basePath(): ?string { return null; }
    #[Override]
    public function environment(): ?string { return null; }
    #[Override]
    public function isDebug(): ?bool { return null; }
    #[Override]
    public function validatorFactory(): ?object
    {
        $this->factoryCalls++;
        return $this->factory;
    }
}
