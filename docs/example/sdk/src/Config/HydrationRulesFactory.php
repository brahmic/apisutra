<?php

declare(strict_types=1);

namespace Example\Records\Config;

use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\FieldRule;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Example\Records\Resources\Records\Get\GetRecordResponseDto;

final class HydrationRulesFactory
{
    public static function create(): HydrationRules
    {
        return HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
            ->withDto(
                GetRecordResponseDto::class,
                DtoRules::create()
                    ->field('id', FieldRule::create()->from('record_id')->required())
                    ->extras('_extra'),
            );
    }
}
