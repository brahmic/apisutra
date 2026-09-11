<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Execution;

use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class CompositeExecutor
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestCollection $requests,
        private readonly ?PipelineContext $parent = null,
    ) {}

    public function execute(ExecutionMode $mode, FailStrategy $strategy): ResultCollection
    {
        $batch = new BatchExecutor(
            client: $this->client,
            requests: $this->requests,
            mode: $mode,
            failStrategy: $strategy,
            parent: $this->parent,
            role: RequestRole::Nested,
        );

        return ResultCollection::make($batch->execute());
    }
}
