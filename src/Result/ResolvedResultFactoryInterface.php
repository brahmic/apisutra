<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Result;

/**
 * Фабрика результата для клиента.
 */
interface ResolvedResultFactoryInterface
{
    public function make(ExecutionResult $result): ResolvedResultInterface;
}
