<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Dsc005\Clarification;

use Brahmic\ApiSutra\Attributes\AttributeMetadataCache;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\DataTransfer\AbstractDto;
use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Serialization\DtoSerializer;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ContinuationStartRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\Workflow\Dsc005\Fixtures\DefaultFinalDto;
use Brahmic\ApiSutra\Workflow\Dsc005\Fixtures\ExtraDto;
use ReflectionClass;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
require __DIR__ . '/Fixtures/DefaultFinalDto.php';
require __DIR__ . '/Fixtures/ExtraDto.php';

$checks = [];
$observations = [];
$transport = new MockTransport();
$transport->preventStrayRequests();
$client = new TestClient(new ClientConfig(
    baseUrl: 'https://api.test',
    environment: Environment::Testing,
    defaultContinuationMode: ContinuationMode::Auto,
), $transport);

// Промежуточные данные не содержат ни одного поля финального DTO.
foreach (['without_unwrap', 'missing_unwrap', 'null_unwrap'] as $case) {
    $payload = ['operationToken' => 'synthetic-token', 'status' => 'pending'];
    if ($case === 'null_unwrap') {
        $payload['data'] = null;
    }
    $result = new ExecutionResult($payload, ResultStatus::SUCCESS, new ErrorCollection([]));
    $dto = $client->continuation()->awaitFromStartResult(
        $result,
        sourceRequest: $case === 'without_unwrap' ? null : new ContinuationStartRequest('synthetic'),
        finalTypeOverride: DefaultFinalDto::class,
    );
    $observations[$case] = ['class' => $dto::class, 'value' => $dto->value];
    $checks[$case . '_false_ready'] = $dto instanceof DefaultFinalDto && $dto->value === 'default';
}
$checks['false_ready_without_http'] = $transport->getRecorded() === [];

// Эквивалентность гидраторов нельзя выводить только из неактивности registry.
$uncached = Hydrator::default();
$cached = new Hydrator(new CastRegistry(), new AttributeMetadataCache());
$plainFirst = $uncached->hydrate([], DefaultFinalDto::class);
$plainSecond = $uncached->hydrate([], DefaultFinalDto::class);
$cachedFirst = $cached->hydrate([], DefaultFinalDto::class);
$cachedSecond = $cached->hydrate([], DefaultFinalDto::class);
$cachedFirst->metadata->marker = 'synthetic-change';
$observations['object_defaults'] = [
    'uncached_same_object' => $plainFirst->metadata === $plainSecond->metadata,
    'cached_same_object' => $cachedFirst->metadata === $cachedSecond->metadata,
    'second_cached_marker' => $cachedSecond->metadata->marker ?? null,
];
$checks['uncached_defaults_independent'] = $observations['object_defaults']['uncached_same_object'] === false;
$checks['cached_defaults_shared'] = $observations['object_defaults']['cached_same_object'] === true;
$checks['cached_default_mutation_leaks'] = $observations['object_defaults']['second_cached_marker'] === 'synthetic-change';

$decoded = json_decode('{"1":{"meta":false},"01":{"meta":0}}', true, flags: JSON_THROW_ON_ERROR);
$observations['decoded_keys'] = array_keys($decoded);
$checks['numeric_string_key_becomes_int'] = $observations['decoded_keys'] === [1, '01'];
$checks['decoded_object_list_origin_lost'] = json_decode('{"0":"a","1":"b"}', true, flags: JSON_THROW_ON_ERROR)
    === json_decode('["a","b"]', true, flags: JSON_THROW_ON_ERROR);
$checks['empty_object_list_origin_lost'] = json_decode('{}', true, flags: JSON_THROW_ON_ERROR)
    === json_decode('[]', true, flags: JSON_THROW_ON_ERROR);

// Это проверка рекурсивного формата предложения В10, а не сборщика extras.
$records = [['sourceKey' => 0, 'remainder' => [['sourceKey' => 1, 'remainder' => ['meta' => 'b']]]]];
$observations['recursive_remainder'] = json_encode($records, JSON_THROW_ON_ERROR);
$checks['recursive_carriers_are_lists'] = $observations['recursive_remainder']
    === '[{"sourceKey":0,"remainder":[{"sourceKey":1,"remainder":{"meta":"b"}}]}]';
$dto = new ExtraDto(7, ['groups' => $records]);
$observations['recursive_wire'] = json_encode(DtoSerializer::default()->serialize($dto), JSON_THROW_ON_ERROR);
$checks['serializer_preserves_recursive_carriers'] = $observations['recursive_wire']
    === '{"id":7,"extra":{"groups":[{"sourceKey":0,"remainder":[{"sourceKey":1,"remainder":{"meta":"b"}}]}]}}';

foreach ([AbstractDto::class, AbstractResponseDto::class] as $base) {
    $observations['base_attributes'][$base] = count((new ReflectionClass($base))->getAttributes());
}
$checks['base_dto_has_no_class_attributes'] = $observations['base_attributes'][AbstractDto::class] === 0;
$checks['base_response_has_no_class_attributes'] = $observations['base_attributes'][AbstractResponseDto::class] === 0;

echo json_encode(['checks' => $checks, 'observations' => $observations], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
exit(in_array(false, $checks, true) ? 1 : 0);
