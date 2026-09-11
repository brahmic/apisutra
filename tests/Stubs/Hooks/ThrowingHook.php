<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Hooks;

use Brahmic\ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use RuntimeException;

final class ThrowingHook implements HookInterface
{
    public function __construct(
        private string $message = 'Ошибка хука',
    ) {}

    public function handle(PipelineContext $context): ?array
    {
        throw new RuntimeException($this->message);
    }
}
