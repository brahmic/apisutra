<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Support;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Pipeline\PipelineExecutorInterface;
use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class RecordingPipelineExecutor implements PipelineExecutorInterface
{
    public int $calls = 0;
    public ?RequestInterface $lastRequest = null;

    public function __construct(
        private mixed $data = null,
        private ResultStatus $status = ResultStatus::SUCCESS,
    ) {}

    public function execute(
        RequestInterface $request,
        RequestRole $role = RequestRole::Root,
        ?PipelineContext $parent = null,
        ?string $traceId = null,
        bool $skipComposite = false,
        bool $skipValidation = false,
    ): ExecutionResult {
        $this->calls++;
        $this->lastRequest = $request;

        return new ExecutionResult(
            data: $this->data,
            status: $this->status,
            errors: new ErrorCollection([]),
        );
    }
}
