<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Dsc005\Fixtures;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use Brahmic\ApiSutra\Enums\DataTransfer\ValueState;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final class CaptureHandler implements CastInterface, DefaultValueProviderInterface
{
    /** @var list<array{kind: string, context: ?string}> */
    public static array $calls = [];

    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        self::$calls[] = ['kind' => 'cast', 'context' => $context === null ? null : $context::class];
        return $value;
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }

    public function resolve(mixed $value, ValueState $state, array $source, ?PipelineContext $context): mixed
    {
        self::$calls[] = ['kind' => 'provider', 'context' => $context === null ? null : $context::class];
        // Снимок входа ребёнка нужен для проверки each и discriminator, это не сбор extras.
        return $source;
    }
}
