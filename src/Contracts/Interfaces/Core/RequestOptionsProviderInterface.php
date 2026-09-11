<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Core;

use Brahmic\ApiSutra\Request\RequestOptions;

interface RequestOptionsProviderInterface
{
    public function getOptions(): RequestOptions;
}
