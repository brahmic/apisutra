<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;

final class AppProvider implements ContainerProviderInterface
{
    public array $calls = [];

    public function bound(string $id): bool
    {
        $this->calls[] = 'bound';

        return false;
    }

    public function make(string $id): ?object
    {
        $this->calls[] = 'make';

        return null;
    }

    public function basePath(): ?string
    {
        $this->calls[] = 'basePath';

        return null;
    }

    public function environment(): ?string
    {
        $this->calls[] = 'environment';

        return 'testing';
    }

    public function isDebug(): ?bool
    {
        $this->calls[] = 'isDebug';

        return true;
    }

    public function validatorFactory(): ?object
    {
        $this->calls[] = 'validatorFactory';

        return null;
    }
}
