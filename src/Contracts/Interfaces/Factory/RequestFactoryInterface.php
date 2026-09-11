<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Factory;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Illuminate\Http\Request;

interface RequestFactoryInterface
{
    /**
     * Создать и заполнить Request из источника данных (Laravel/array)
     */
    public function make(string $requestClass, Request|array $source): RequestInterface;
}
