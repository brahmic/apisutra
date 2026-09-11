<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Core;

use Brahmic\ApiSutra\Enums\Execution\RequestRole;
use Brahmic\ApiSutra\Enums\Execution\SendMode;
use Brahmic\ApiSutra\Result\ResultHandle;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

/** Вложенная отправка с наследованием контекста и срока родителя. */
interface ContextualClientInterface extends ClientInterface
{
    public function sendInContext(RequestInterface $request, PipelineContext $parent, RequestRole $role, SendMode $mode = SendMode::Sync): ResultHandle;
}
