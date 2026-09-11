<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Laravel\RequestFactory;

interface ResolverInterface
{
    public function supports(ResolveContext $context): bool;

    public function resolve(ResolveContext $context): mixed;
}
