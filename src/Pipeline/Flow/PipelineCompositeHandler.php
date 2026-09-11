<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Pipeline\Flow;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\DependsOnRequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Pipeline\Execution\CompositeFlow;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class PipelineCompositeHandler
{
    public function __construct(
        private CompositeFlow $compositeFlow,
    ) {}

    public function handle(
        RequestInterface $request,
        PipelineContext $context,
        bool $skipComposite,
    ): ?ExecutionResult {
        return match (true) {
            $skipComposite => null,
            $request instanceof CompositeRequestInterface => $this->compositeFlow->executeComposite($request, $context),
            $request instanceof DependsOnRequestInterface => $this->compositeFlow->executeDependsOn($request, $context),
            default => null,
        };
    }
}
