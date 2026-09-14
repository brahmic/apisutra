<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit;

use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ListShapeProvider implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, ?PipelineContext $context): mixed
    {
        // Возвращаем проверенное значение без замены; Nested выполнится следующим.
        return (new StrictListCast())->hydrate($value, $context);
    }
}
