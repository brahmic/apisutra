<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Pln031\Cost;

use Brahmic\ApiSutra\Attributes\AttributeMetadataCache;
use Brahmic\ApiSutra\Attributes\DataTransfer\Cast;
use Brahmic\ApiSutra\Attributes\DataTransfer\From;
use Brahmic\ApiSutra\Attributes\DataTransfer\Map;
use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Casts\IntegerCast;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Serialization\DtoSerializer;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Serializer;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\CastDto;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\CastRequest;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\DefaultsDto;

// Измерение стоимости metadata cache до и после 031; запускается из корня пакета.
$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';
foreach (glob(__DIR__ . '/Fixtures/*.php') ?: [] as $fixture) {
    require_once $fixture;
}

/** DTO только со значениями в декларациях. */
final readonly class ValueDeclarationsDto
{
    public function __construct(
        #[From('record_id')] public int $id = 0,
        #[Map('title_text')] public string $title = '',
        public ?int $count = null,
        public bool $active = false,
        #[Cast(IntegerCast::class)] public int $score = 0,
    ) {
    }
}

#[Get('/values')]
final class ValueDeclarationsRequest extends AbstractRequest
{
    public function __construct(
        #[Query] public int $id = 7,
        #[Query] public string $title = 'title',
        #[Query] #[Cast(IntegerCast::class)] public int $score = 3,
    ) {
    }
}

const RUNS = 7;

/**
 * @param callable(): void $operation
 * @return array{iterations: int, median_ms: float, min_ms: float, max_ms: float, spread_ms: float, retained_bytes_max: int}
 */
function measure(callable $operation, int $iterations): array
{
    $operation();
    $times = [];
    $retained = [];
    for ($run = 0; $run < RUNS; $run++) {
        gc_collect_cycles();
        $before = memory_get_usage();
        $start = hrtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $operation();
        }
        $times[] = (hrtime(true) - $start) / 1e6;
        gc_collect_cycles();
        $retained[] = memory_get_usage() - $before;
    }
    sort($times);

    return [
        'iterations' => $iterations,
        'median_ms' => round($times[intdiv(RUNS, 2)], 3),
        'min_ms' => round($times[0], 3),
        'max_ms' => round($times[RUNS - 1], 3),
        'spread_ms' => round($times[RUNS - 1] - $times[0], 3),
        'retained_bytes_max' => max($retained),
    ];
}

$valuePayload = ['record_id' => 7, 'title_text' => 'title', 'count' => 3, 'active' => true, 'score' => '5'];
$valueItems = array_fill(0, 100, $valuePayload);
$objectItems = array_fill(0, 100, []);
$config = new ClientConfig(baseUrl: 'https://cost.test');
$scenarios = [];

$modes = [
    'warm' => static fn (): AttributeMetadataCache => new AttributeMetadataCache(true),
    'cold' => null,
    'off' => static fn (): AttributeMetadataCache => new AttributeMetadataCache(false),
];

foreach (['values' => [ValueDeclarationsDto::class, $valuePayload, $valueItems], 'objects' => [DefaultsDto::class, [], $objectItems]] as $kind => [$class, $payload, $items]) {
    foreach ($modes as $mode => $factory) {
        $shared = $factory === null ? null : new Hydrator(new CastRegistry(), $factory());
        $hydrator = static fn (): Hydrator => $shared ?? new Hydrator(new CastRegistry(), new AttributeMetadataCache(true));
        $scenarios["hydrator.$kind.$mode.single"] = measure(
            static function () use ($hydrator, $class, $payload): void {
                $hydrator()->hydrate($payload, $class);
            },
            $mode === 'warm' ? 2000 : 300,
        );
        $scenarios["hydrator.$kind.$mode.collection"] = measure(
            static function () use ($hydrator, $class, $items): void {
                $hydrator()->hydrateCollection($items, $class);
            },
            $mode === 'warm' ? 60 : 20,
        );
    }
}

foreach ($modes as $mode => $factory) {
    $shared = $factory === null ? null : new Hydrator(new CastRegistry(), $factory());
    $hydrator = static fn (): Hydrator => $shared ?? new Hydrator(new CastRegistry(), new AttributeMetadataCache(true));
    $scenarios["hydrator.cast-object.$mode.single"] = measure(
        static function () use ($hydrator): void {
            $hydrator()->hydrate(['number' => 1], CastDto::class);
        },
        $mode === 'warm' ? 2000 : 300,
    );
}

$valueDto = new ValueDeclarationsDto(7, 'title', 3, true, 5);
$objectDto = new CastDto();
foreach (['values' => $valueDto, 'objects' => $objectDto] as $kind => $dto) {
    foreach ($modes as $mode => $factory) {
        $shared = $factory === null ? null : new DtoSerializer(new CastRegistry(), $factory());
        $scenarios["dto-serializer.$kind.$mode.single"] = measure(
            static function () use ($shared, $dto): void {
                ($shared ?? new DtoSerializer(new CastRegistry(), new AttributeMetadataCache(true)))->serialize($dto);
            },
            $mode === 'warm' ? 2000 : 300,
        );
    }
}

foreach (['values' => ValueDeclarationsRequest::class, 'objects' => CastRequest::class] as $kind => $requestClass) {
    foreach ($modes as $mode => $factory) {
        $shared = $factory === null ? null : new Serializer(new CastRegistry(), $factory());
        $scenarios["request-parts.$kind.$mode.single"] = measure(
            static function () use ($shared, $requestClass, $config): void {
                $request = new $requestClass();
                ($shared ?? new Serializer(new CastRegistry(), new AttributeMetadataCache(true)))
                    ->serialize($request, new PipelineContext($request, $config, 'cost'));
            },
            $mode === 'warm' ? 1000 : 200,
        );
    }
}

$head = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null'));

echo json_encode([
    'head' => $head,
    'php' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'opcache_enable_cli' => ini_get('opcache.enable_cli'),
    'opcache_jit' => ini_get('opcache.jit'),
    'xdebug' => extension_loaded('xdebug'),
    'os' => php_uname('s') . ' ' . php_uname('r'),
    'runs' => RUNS,
    'note' => 'warm — один экземпляр и прогретый кеш; cold — новый экземпляр и кеш на каждую операцию; off — выключенный кеш',
    'peak_memory_bytes' => memory_get_peak_usage(true),
    'scenarios' => $scenarios,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
