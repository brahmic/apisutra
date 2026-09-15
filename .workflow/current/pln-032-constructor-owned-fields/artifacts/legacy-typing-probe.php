<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\FieldRule;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarValues;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

// Локальная модель изолирует запись простых полей от конструкторов и профилей.
$model = new class {
    public float|string $floatString;
    public string|float $stringFloat;
    public bool|int $boolInt;
    public int|bool $intBool;
    public int $number;
    public string $text;
};
$class = $model::class;
$base = ['floatString' => '', 'stringFloat' => '', 'boolInt' => false, 'intBool' => false, 'number' => 0, 'text' => ''];

// Ожидания заданы вручную: три последних значения — Hydrator, setValue и coerce.
$cases = [
    ['floatString', 5, '5', 5.0, '5'],
    ['stringFloat', 5, '5', 5.0, '5'],
    ['boolInt', 'false', false, true, false],
    ['intBool', 'false', false, true, false],
    ['number', '7', 7, 7, 7],
    ['text', true, '1', '1', '1'],
    ['intBool', '1', 1, 1, 1],
];
$typed = static fn (mixed $value): array => ['type' => get_debug_type($value), 'value' => $value];
$observations = [];
$failures = [];
foreach ($cases as [$name, $input, $expectedHydrator, $expectedReflection, $expectedHelper]) {
    $property = new ReflectionProperty($class, $name);
    $type = $property->getType();
    $types = $type instanceof ReflectionUnionType
        ? array_map(static fn (ReflectionNamedType $part): string => $part->getName(), $type->getTypes())
        : [$type->getName()];
    $payload = array_replace($base, [$name => $input]);
    $ordinary = Hydrator::default()->hydrate($payload, $class)->{$name};
    $reflectionDto = new $class();
    $property->setValue($reflectionDto, $input);
    $reflection = $reflectionDto->{$name};
    $helper = (new ScalarValues())->coerce($input, $types, ScalarPolicy::Legacy);
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Legacy))
        ->withDto($class, DtoRules::create()->field($name, FieldRule::create()->noTransform()));
    $withoutTransform = Hydrator::forRules($rules)->hydrate($payload, $class)->{$name};
    $observations[] = [
        'field' => $name,
        'input' => $typed($input),
        'reflection_types' => $types,
        'hydrator' => $typed($ordinary),
        'setValue' => $typed($reflection),
        'coerce' => $typed($helper),
        'hydrator_noTransform' => $typed($withoutTransform),
    ];
    if (
        $ordinary !== $expectedHydrator
        || $reflection !== $expectedReflection
        || $helper !== $expectedHelper
        || $withoutTransform !== $expectedReflection
    ) {
        $failures[] = $name . ':' . json_encode($input, JSON_THROW_ON_ERROR);
    }
}
echo json_encode(
    ['observations' => $observations, 'verified' => count($cases) - count($failures), 'failures' => $failures],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
), PHP_EOL;
exit($failures === [] ? 0 : 1);
