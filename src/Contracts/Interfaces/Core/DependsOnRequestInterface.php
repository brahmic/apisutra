<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Core;

use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

interface DependsOnRequestInterface extends RequestInterface
{
    /**
     * Запросы-зависимости
     */
    public function dependencies(): RequestCollection;

    /**
     * Обработка результатов зависимостей перед основным запросом
     */
    public function processDependencies(ResultCollection $results, PipelineContext $ctx): void;
}
