<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Request;

use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Result\ResolvedResultFactoryInterface;
use Brahmic\ApiSutra\Result\ResolvedResultInterface;

final class TestResolvedResultFactory implements ResolvedResultFactoryInterface
{
    public function make(ExecutionResult $result): ResolvedResultInterface
    {
        return new TestResolvedResult($result);
    }
}
