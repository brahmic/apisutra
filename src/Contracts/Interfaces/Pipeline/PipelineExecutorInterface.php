<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Pipeline;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

interface PipelineExecutorInterface
{
    public function execute(
        RequestInterface $request,
        RequestRole $role = RequestRole::Root,
        ?PipelineContext $parent = null,
        ?string $traceId = null,
        bool $skipComposite = false,
        bool $skipValidation = false,
    ): ExecutionResult;
}
