<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hooks;

use Brahmic\ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class NamedHook implements HookInterface
{
    public function __construct(
        private string $name,
    ) {}

    public function handle(PipelineContext $context): ?array
    {
        HookRecorder::add($this->name);
        return null;
    }
}
