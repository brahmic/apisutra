<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Core;

use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

interface CompositeRequestInterface extends RequestInterface
{
    /**
     * Дочерние запросы для выполнения
     */
    public function requests(): RequestCollection;

    /**
     * Агрегация результатов в единый DTO
     */
    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed;
}
