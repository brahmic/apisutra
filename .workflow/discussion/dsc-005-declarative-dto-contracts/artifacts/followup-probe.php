<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Dsc005\Followup;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Serialization\DtoSerializer;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\Workflow\Dsc005\Fixtures\ExtraDto;
use ReflectionClass;
use Throwable;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
require __DIR__ . '/Fixtures/ExtraDto.php';

/** @return array{class: ?string, reason: ?string, path: ?string, previous: ?string} */
function failure(callable $operation): array
{
    try {
        $operation();
        return ['class' => null, 'reason' => null, 'path' => null, 'previous' => null];
    } catch (Throwable $error) {
        return [
            'class' => $error::class,
            'reason' => $error instanceof HydrationException ? $error->reason : null,
            'path' => $error instanceof HydrationException ? $error->path : null,
            'previous' => $error->getPrevious() === null ? null : $error->getPrevious()::class,
        ];
    }
}

$checks = [];
$observations = [];
$reflection = new ReflectionClass(ExtraDto::class);
$checks['plain_readonly_fixture'] = $reflection->isReadOnly()
    && $reflection->getParentClass() === false && $reflection->getInterfaceNames() === []
    && $reflection->getAttributes() === [];
$bad = ['id' => [], 'extra' => []];
$observations['plain_direct'] = failure(fn () => Hydrator::default()->hydrate($bad, ExtraDto::class));
$checks['plain_direct_structured'] = $observations['plain_direct']['class'] === HydrationException::class
    && $observations['plain_direct']['reason'] === 'invalid_field_type'
    && $observations['plain_direct']['path'] === 'id';
$transport = new MockTransport();
$transport->preventStrayRequests();
$client = new TestClient(new ClientConfig(
    baseUrl: 'https://api.test',
    environment: Environment::Testing,
    defaultContinuationMode: ContinuationMode::Sync,
), $transport);
$result = new ExecutionResult($bad, ResultStatus::SUCCESS, new ErrorCollection([]));
$observations['plain_sync'] = failure(fn () => $client->continuation()->awaitFromStartResult(
    $result,
    finalTypeOverride: ExtraDto::class,
));
$checks['plain_await_loses_error'] = $observations['plain_sync'] === [
    'class' => ContinuationConfigurationException::class,
    'reason' => null,
    'path' => null,
    'previous' => null,
];
$checks['no_http'] = $transport->getRecorded() === [];

// Это проверка формата предложенных данных, а не реализация сбора extras.
$dense = [0 => ['meta' => 'a'], 1 => ['meta' => 'b']];
$sparse = [1 => ['meta' => 'b']];
$observations['native_dense'] = json_encode($dense, JSON_THROW_ON_ERROR);
$observations['native_sparse'] = json_encode($sparse, JSON_THROW_ON_ERROR);
$checks['native_dense_json_list'] = $observations['native_dense'] === '[{"meta":"a"},{"meta":"b"}]';
$checks['native_sparse_json_object'] = $observations['native_sparse'] === '{"1":{"meta":"b"}}';

// Стабильная альтернатива: плотный список остатков с явным исходным ключом.
$stableDense = [
    ['sourceKey' => 0, 'remainder' => ['meta' => 'a']],
    ['sourceKey' => 1, 'remainder' => ['meta' => 'b']],
];
$stableSparse = [['sourceKey' => 1, 'remainder' => ['meta' => 'b']]];
$stableDictionary = [['sourceKey' => 'record-b', 'remainder' => ['meta' => false]]];
$observations['stable_dense'] = json_encode($stableDense, JSON_THROW_ON_ERROR);
$observations['stable_sparse'] = json_encode($stableSparse, JSON_THROW_ON_ERROR);
$observations['stable_dictionary'] = json_encode($stableDictionary, JSON_THROW_ON_ERROR);
$checks['stable_dense_json_list'] = $observations['stable_dense']
    === '[{"sourceKey":0,"remainder":{"meta":"a"}},{"sourceKey":1,"remainder":{"meta":"b"}}]';
$checks['stable_sparse_json_list'] = $observations['stable_sparse']
    === '[{"sourceKey":1,"remainder":{"meta":"b"}}]';
$checks['dictionary_key_and_false_preserved'] = $observations['stable_dictionary']
    === '[{"sourceKey":"record-b","remainder":{"meta":false}}]';
$dto = new ExtraDto(7, ['rows' => $stableSparse]);
$observations['dto_wire'] = json_encode(DtoSerializer::default()->serialize($dto), JSON_THROW_ON_ERROR);
$checks['ordinary_serializer_preserves_carrier'] = $observations['dto_wire']
    === '{"id":7,"extra":{"rows":[{"sourceKey":1,"remainder":{"meta":"b"}}]}}';

echo json_encode(['checks' => $checks, 'observations' => $observations], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
exit(in_array(false, $checks, true) ? 1 : 0);
