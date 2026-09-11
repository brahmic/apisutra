<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hooks;

use Brahmic\ApiSutra\Contracts\Interfaces\Hooks\BeforeHydrateHookInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class OverrideBeforeHydrateHook implements BeforeHydrateHookInterface
{
    public function handle(PipelineContext $context): array
    {
        return [
            'id' => 99,
            'name' => 'override',
        ];
    }
}
