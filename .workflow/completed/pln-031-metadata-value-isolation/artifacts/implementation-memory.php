<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Pln031;

use Brahmic\ApiSutra\Attributes\AttributeMetadataCache;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Serialization\DtoSerializer;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Serializer;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\CastDto;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\CastRequest;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\DefaultsDto;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
foreach (glob(__DIR__ . '/Fixtures/*.php') as $fixture) {
    require_once $fixture;
}

$hydrator = new Hydrator(new CastRegistry(), new AttributeMetadataCache());
$dtoSerializer = new DtoSerializer(new CastRegistry(), new AttributeMetadataCache());
$serializer = new Serializer(new CastRegistry(), new AttributeMetadataCache());
$dto = new CastDto();
$request = new CastRequest();
$context = new PipelineContext($request, new ClientConfig(baseUrl: 'https://memory.test'), 'memory');
$operations = [
    'hydrator-default' => fn () => $hydrator->hydrate([], DefaultsDto::class),
    'hydrator-cast' => fn () => $hydrator->hydrate(['number' => 0], CastDto::class),
    'dto-serializer' => fn () => $dtoSerializer->serialize($dto),
    'request-parts' => fn () => $serializer->serialize($request, $context),
    'cold-hydrator-cast' => fn () => (new Hydrator(new CastRegistry(), new AttributeMetadataCache()))
        ->hydrate(['number' => 0], CastDto::class),
];

$results = [];
foreach ($operations as $name => $operation) {
    // Прогреваем также служебные структуры PHP; разовая аллокация не означает удержания DTO.
    for ($i = 0; $i < 2400; $i++) {
        $operation();
    }
    foreach ([1200, 2400, 4800] as $iterations) {
        gc_collect_cycles();
        $before = memory_get_usage();
        for ($i = 0; $i < $iterations; $i++) {
            $operation();
        }
        gc_collect_cycles();
        $retained = memory_get_usage() - $before;
        $results[$name][] = ['iterations' => $iterations, 'retained_bytes' => $retained];
    }
}

$passed = true;
foreach ($results as $samples) {
    foreach ($samples as $sample) {
        $passed = $passed && $sample['retained_bytes'] === 0;
    }
}
echo json_encode(['passed' => $passed, 'samples' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
exit($passed ? 0 : 1);
