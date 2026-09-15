<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use Brahmic\ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\FieldRule;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Brahmic\ApiSutra\Serialization\Rules\ValueShape;

final class CatalogRules
{
    public static function create(): HydrationRules
    {
        return HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
            ->withDto(CatalogItemDto::class, DtoRules::create()
                ->field('relatedIds', FieldRule::create()->from('related_ids')->required()
                    ->shape(ValueShape::list(ValueShape::int())))
                ->field('media', FieldRule::create()->from('assets')->required()
                    ->shape(ValueShape::list(
                        ValueShape::variants('type', [
                            'image' => ImageDto::class,
                            'video' => VideoDto::class,
                        ], unknown: NestedUnknownVariant::Error),
                        each: 'value',
                    )))
                ->field('stock', FieldRule::create()->forbidExplicitNull())
                ->extras('_extra'))
            ->withDto(SellerDto::class, DtoRules::create()->extras('_extra'));
    }
}
