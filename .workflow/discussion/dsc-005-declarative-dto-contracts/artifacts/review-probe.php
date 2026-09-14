<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Dsc005;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Continuation\ContinuationAwaitOptions;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Result\ContinuationTokenExtractorInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Result\ResolvedResultFactory;
use Brahmic\ApiSutra\Result\ResultHandle;
use Brahmic\ApiSutra\Serialization\DtoSerializer;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Dto\ContinuationFinalDto;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Brahmic\ApiSutra\Workflow\Dsc005\Fixtures\CaptureHandler;
use Brahmic\ApiSutra\Workflow\Dsc005\Fixtures\EnvelopeDto;
use Brahmic\ApiSutra\Workflow\Dsc005\Fixtures\ExtraDto;
use Throwable;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
require __DIR__ . '/Fixtures/CaptureHandler.php';
require __DIR__ . '/Fixtures/CaptureDto.php';
require __DIR__ . '/Fixtures/EnvelopeDto.php';
require __DIR__ . '/Fixtures/ExtraDto.php';

/** @return array{class: ?string, reason: ?string, path: ?string, previous: ?string, message: string} */
function failure(callable $operation): array
{
    try {
        $operation();
        return ['class' => null, 'reason' => null, 'path' => null, 'previous' => null, 'message' => ''];
    } catch (Throwable $error) {
        return [
            'class' => $error::class,
            'reason' => $error instanceof HydrationException ? $error->reason : null,
            'path' => $error instanceof HydrationException ? $error->path : null,
            'previous' => $error->getPrevious() === null ? null : $error->getPrevious()::class,
            'message' => $error->getMessage(),
        ];
    }
}

/** @param array<string, mixed> $data */
function result(array $data): ExecutionResult
{
    return new ExecutionResult($data, ResultStatus::SUCCESS, new ErrorCollection([]));
}

$extractor = new class implements ContinuationTokenExtractorInterface {
    public function extract(ExecutionResult $result): ?string
    {
        return is_array($result->data) && is_string($result->data['operationToken'] ?? null)
            ? $result->data['operationToken']
            : null;
    }
};

$config = new ClientConfig(
    baseUrl: 'https://api.test',
    environment: Environment::Testing,
    defaultPollRequest: ContinuationPollRequest::class,
    continuationTokenExtractor: $extractor,
);
$checks = [];
$observations = [];
$bad = ['value' => []];
$direct = failure(fn () => Hydrator::default()->hydrate($bad, ContinuationFinalDto::class));
$observations['direct'] = $direct;
$checks['direct_structured_error'] = $direct['class'] === HydrationException::class
    && $direct['reason'] === 'invalid_field_type' && $direct['path'] === 'value';

foreach ([true, false] as $hasToken) {
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([
        ContinuationPollRequest::class => MockResponse::success(
            $bad + ($hasToken ? ['operationToken' => 'synthetic-token'] : []),
        ),
    ]);
    $client = new TestClient($config, $transport);
    $error = failure(fn () => $client->continuation()->awaitByTokenAs(
        'synthetic-token',
        ContinuationFinalDto::class,
        new ContinuationAwaitOptions(maxAttempts: 2, intervalMs: 0),
    ));
    $id = $hasToken ? 'poll_with_token' : 'poll_without_token';
    $observations[$id] = $error + ['requests' => count($transport->getRecorded())];
    $checks[$id] = $error['class'] === ContinuationConfigurationException::class
        && $error['previous'] === null
        && count($transport->getRecorded()) === ($hasToken ? 2 : 1)
        && str_contains($error['message'], $hasToken ? 'лимит polling попыток' : 'не вернул финальные данные');
}

$transport = new MockTransport();
$transport->preventStrayRequests();
$syncClient = new TestClient($config->with(defaultContinuationMode: ContinuationMode::Sync), $transport);
$syncError = failure(fn () => $syncClient->continuation()->awaitFromStartResult(
    result($bad),
    finalTypeOverride: ContinuationFinalDto::class,
));
$observations['sync_invalid'] = $syncError;
$checks['sync_invalid'] = $syncError['class'] === ContinuationConfigurationException::class
    && str_contains($syncError['message'], 'режиме Sync') && $syncError['previous'] === null;

$handle = new ResultHandle(result($bad), new ResolvedResultFactory(), $syncClient);
$checks['untyped_await'] = $handle->await() === $bad;
$cachedError = failure(fn () => $handle->awaitAs(ContinuationFinalDto::class));
$observations['cached_invalid'] = $cachedError;
$checks['cached_error_loses_previous'] = $cachedError['class'] === ContinuationConfigurationException::class
    && $cachedError['reason'] === null && $cachedError['path'] === null && $cachedError['previous'] === null;
$checks['sync_and_cached_do_not_poll'] = count($transport->getRecorded()) === 0;
$checks['explicit_null_override'] = $config->with(continuationTokenExtractor: null)->continuationTokenExtractor === null;

$payload = [
    'each' => [['value' => ['id' => 7, 'probe' => 'ok'], 'meta' => ['revision' => 2]]],
    'value' => [['type' => 'known', 'id' => 7, 'probe' => 'ok']],
    'key' => [['known' => ['id' => 7, 'probe' => 'ok'], 'meta' => ['revision' => 2]]],
];
$dto = Hydrator::default()->hydrate($payload, EnvelopeDto::class);
$observations['child_inputs'] = [
    'each' => $dto->each[0]->source,
    'value' => $dto->value[0]->source,
    'key' => $dto->key[0]->source,
];
$checks['each_hides_wrapper_meta'] = $dto->each[0]->source === ['id' => 7, 'probe' => 'ok'];
$checks['value_keeps_discriminator'] = $dto->value[0]->source === ['type' => 'known', 'id' => 7, 'probe' => 'ok'];
$checks['key_hides_wrapper_and_meta'] = $dto->key[0]->source === ['id' => 7, 'probe' => 'ok'];
$observations['standalone_callbacks'] = CaptureHandler::$calls;
$checks['standalone_null_context'] = count(CaptureHandler::$calls) === 6
    && array_unique(array_column(CaptureHandler::$calls, 'context')) === [null];
CaptureHandler::$calls = [];
$context = new PipelineContext(new SimpleGetRequest('synthetic'), $config, 'synthetic-trace');
Hydrator::default()->hydrate($payload, EnvelopeDto::class, $context);
$observations['http_context_callbacks'] = CaptureHandler::$calls;
$checks['callbacks_receive_pipeline_context'] = count(CaptureHandler::$calls) === 6
    && array_unique(array_column(CaptureHandler::$calls, 'context')) === [PipelineContext::class];
$checks['pipeline_context_has_no_hydrator'] = !property_exists($context, 'hydrator');

$plain = new ExtraDto(7, ['future' => false]);
$observations['ordinary_extra_serialization'] = DtoSerializer::default()->serialize($plain);
$checks['extra_serializes_under_own_name'] = $observations['ordinary_extra_serialization']
    === ['id' => 7, 'extra' => ['future' => false]];
$checks['json_integer_token'] = is_int(json_decode('10', flags: JSON_THROW_ON_ERROR));
$checks['json_fraction_token'] = is_float(json_decode('10.0', flags: JSON_THROW_ON_ERROR));

foreach ([9007199254740991, 9007199254740992, 9007199254740993, 9007199254740994] as $integer) {
    $observations['integer_to_float'][(string) $integer] = sprintf('%.0f', (float) $integer);
}
$checks['two_to_53_exact'] = $observations['integer_to_float']['9007199254740992'] === '9007199254740992';
$checks['two_to_53_plus_one_loses_value'] = $observations['integer_to_float']['9007199254740993'] === '9007199254740992';
$checks['larger_even_can_be_exact'] = $observations['integer_to_float']['9007199254740994'] === '9007199254740994';

echo json_encode(['checks' => $checks, 'observations' => $observations], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
exit(in_array(false, $checks, true) ? 1 : 0);
