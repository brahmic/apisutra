<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Tests\Stubs\Casts\UppercaseCast;
use Brahmic\ApiSutra\Tests\Stubs\Dto\CastAttributeDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\CastStringDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\ObjectDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\PlainValueDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\ProviderEnvelopeDto;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Illuminate\\')) {
        throw new RuntimeException('Гидратация загрузила Illuminate: ' . $class);
    }
}, prepend: true);

// Процесс изолирует глобальный registry и проверяет гидратацию без Laravel.
CastRegistry::global()->register('string', new UppercaseCast());
$hydrator = Hydrator::default();
$observations = [
    'registered' => CastRegistry::global()->get('string')->hydrate('lower'),
    'plain' => $hydrator->hydrate(['value' => 'lower'], PlainValueDto::class)->value,
    'profile' => CastStringDto::from(['value' => 'lower'])->value,
    'property' => CastAttributeDto::from(['value' => 'lower'])->value,
    'nested' => $hydrator->hydrate(['address' => ['city' => 'Sample']], ObjectDto::class)->address->city,
];
try {
    $hydrator->hydrate(['child' => ['count' => null]], ProviderEnvelopeDto::class);
} catch (HydrationException $error) {
    $observations['providerPath'] = $error->path;
}
$observations['illuminate'] = array_values(array_filter(
    get_declared_classes(),
    static fn (string $class): bool => str_starts_with($class, 'Illuminate\\'),
));

echo json_encode($observations, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
