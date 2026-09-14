<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Support;

use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\FieldRule;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Brahmic\ApiSutra\Serialization\Rules\ValueShape;
use Brahmic\ApiSutra\Tests\Stubs\HydrationRules\OwnerDto;
use Brahmic\ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use Brahmic\ApiSutra\Tests\Stubs\HydrationRules\ReportDto;
use LogicException;

final readonly class HydrationRulesFixture
{
    public static function rules(): HydrationRules
    {
        return HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
            ->withDto(ReportDto::class, DtoRules::create()
                ->field('id', FieldRule::create()->from('record_id', 'legacy_id'))
                ->field('owner', FieldRule::create()->from('profile')->shape(ValueShape::dto(OwnerDto::class)))
                ->field('items', FieldRule::create()->from('rows')->required()
                    ->shape(ValueShape::list(ValueShape::dto(RecordDto::class), each: 'value')))
                ->field('ids', FieldRule::create()->shape(ValueShape::list(ValueShape::int())))
                ->field('count', FieldRule::create()->forbidExplicitNull())
                ->extras('extra'))
            ->withDto(OwnerDto::class, DtoRules::create()->field('id', FieldRule::create()->from('user_id'))->extras('extra'))
            ->withDto(RecordDto::class, DtoRules::create()->field('id', FieldRule::create()->from('record_id')));
    }

    /** @return array<string, mixed> */
    public static function payload(): array
    {
        return [
            'record_id' => 7, 'legacy_id' => 99,
            'profile' => ['user_id' => 11, 'future' => false],
            'rows' => [['value' => ['record_id' => 12], 'meta' => ['future' => 0]]],
            'ids' => [1, 2], 'future' => null,
        ];
    }

    public static function error(callable $operation): HydrationException
    {
        try {
            $operation();
        } catch (HydrationException $exception) {
            return $exception;
        }
        throw new LogicException('Ожидалась ошибка гидратации');
    }
}
