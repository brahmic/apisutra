<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Requests;

use Brahmic\ApiSutra\Attributes\Behavior\Execution;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Collections\RequestCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\DependsOnRequestInterface;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Execution\ExecutionMode;
use Brahmic\ApiSutra\Enums\Execution\FailStrategy;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/depends-on/parallel')]
#[Execution(mode: ExecutionMode::Parallel, failStrategy: FailStrategy::FailAll)]
final class DependsOnParallelRequest extends AbstractRequest implements DependsOnRequestInterface
{
    public function __construct(
        #[Query]
        public ?string $token = null,
    ) {}

    public function dependencies(): RequestCollection
    {
        return RequestCollection::make([
            new DependencyTokenRequest(),
        ]);
    }

    public function processDependencies(ResultCollection $results, PipelineContext $ctx): void
    {
        $first = $results->all()[0] ?? null;
        $data = $first?->data;
        if (is_array($data) && isset($data['token'])) {
            $this->token = (string) $data['token'];
        }
    }
}
