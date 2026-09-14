<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\Contracts\Interfaces\Casting\ScopedCastInterface;
use Brahmic\ApiSutra\Serialization\Rules\HydrationScope;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use LogicException;

final class CountedRecordCast implements ScopedCastInterface
{
    /** @var list<PipelineContext|null> */
    public static array $contexts = [];

    public function hydrate(mixed $value, ?PipelineContext $context = null): mixed
    {
        throw new LogicException('Должен вызываться scoped-метод');
    }

    public function hydrateInScope(mixed $value, HydrationScope $scope): mixed
    {
        self::$contexts[] = $scope->context();
        return $scope->hydrate($value, CountedRecordDto::class);
    }

    public function serialize(mixed $value, ?PipelineContext $context = null): mixed
    {
        return $value;
    }
}
