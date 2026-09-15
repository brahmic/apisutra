<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Support;

use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\FieldRule;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Brahmic\ApiSutra\Tests\Stubs\ConstructorOwned\ArrayDto;

final readonly class ConstructorOwnedFixture
{
    public static function rules(string $class = ArrayDto::class, ?FieldRule $field = null): HydrationRules
    {
        return HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
            ->withDto($class, DtoRules::create()->field('value', ($field ?? FieldRule::create())->constructorValue()));
    }

    public static function deep(int $depth): array
    {
        $value = [];
        for ($i = 1; $i < $depth; $i++) {
            $value = [$value];
        }
        return $value;
    }
}
