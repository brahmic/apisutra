<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Core;

use Brahmic\ApiSutra\Request\PaginationOptions;

interface RequestExecutionInterface extends RequestInterface, RequestOptionsProviderInterface
{
    public function getRequest(): RequestInterface;

    public function getPaginationOptions(): PaginationOptions;
}
