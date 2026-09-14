<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use Brahmic\ApiSutra\Enums\Serialization\BooleanFormat;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\HttpTransport;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\AuditClient;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\RecordingHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use MaxSutraAudit\ReadLimited;
use MaxSutraAudit\ReadList;
use MaxSutraAudit\ReadRecord;
use MaxSutraAudit\Record;
use MaxSutraAudit\RecordList;
use MaxSutraAudit\StrictRecord;
use MaxSutraAudit\WriteRecord;

// Адаптация стенда: клиент ядра и локальный PSR-18 транспорт вместо классов внешнего SDK.
$root = dirname(__DIR__, 4);
$loader = require $root . '/vendor/autoload.php';
$loader->addPsr4('Brahmic\\ApiSutra\\Tests\\Stubs\\DtoFeedbackAudit\\', __DIR__ . '/Stubs');
foreach (glob($root . '/.workflow/issue/iss-002-apisutra-reuse/artifacts/fixtures/*.php') ?: [] as $fixture) {
    require $fixture;
}

/** @var list<array{id: string, observation: string}> $observations */
$observations = [];
function verify(string $id, bool $condition, string $observation): void
{
    global $observations;
    if (!$condition) {
        throw new RuntimeException('Observation changed or probe failed: '.$id);
    }
    $observations[] = ['id' => $id, 'observation' => $observation];
}

function client(RecordingHttpClient $http, ?ClientConfig $config = null): AuditClient
{
    $factory = new HttpFactory();

    return new AuditClient($config ?? new ClientConfig(
        baseUrl: 'https://example.invalid',
        retry: new RetryConfig(attempts: 1),
        containerProvider: new NullContainerProvider(),
    ), new HttpTransport($http, $factory, $factory));
}

/** @param array<string, mixed>|string $payload */
function execute(AbstractRequest $request, array|string $payload, int $status = 200): ExecutionResult
{
    $http = new RecordingHttpClient([new Response($status, ['Content-Type' => 'application/json'], is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR))]);
    $result = client($http)->send($request)->raw();
    if (count($http->requests) !== 1) {
        throw new RuntimeException('Expected one fixture HTTP attempt.');
    }

    return $result;
}

/** @param array<string, mixed> $payload */
function hydrationError(array $payload, string $type): HydrationException
{
    try {
        Hydrator::default()->hydrate($payload, $type);
    } catch (HydrationException $error) {
        return $error;
    }
    throw new RuntimeException('Expected a hydration error.');
}

$valid = ['id' => 123, 'active' => false, 'title' => '0', 'label' => null];
$result = execute(new ReadRecord(), ['data' => $valid]);
verify('P01', $result->isSuccess() && $result->data instanceof Record, 'Returns/unwrap hydrate an ordinary readonly class; raw() keeps the DTO.');
verify('P02', $result->data == Hydrator::default()->hydrate($valid, Record::class), 'Standalone and request hydration return equivalent values.');

$coerced = Hydrator::default()->hydrate(array_replace($valid, ['id' => '123', 'active' => 'false', 'title' => 0]), Record::class);
verify('P03', $coerced instanceof Record && $coerced->id === 123 && $coerced->active === false && $coerced->title === '0', 'Default scalar hydration coerces numeric strings, textual bools and numeric titles.');
$extra = Hydrator::default()->hydrate($valid + ['future' => ['enabled' => false]], Record::class);
verify('P04', $extra instanceof Record && $extra->extra === [], 'Unknown source fields are not automatically collected into extra.');
$missing = $valid;
unset($missing['label']);
$error = hydrationError($missing, Record::class);
verify('P05', $error->reason === 'required_field_missing' && $error->path === 'label', 'Missing required nullable field is rejected; explicit null is allowed.');
$optional = Hydrator::default()->hydrate($valid + ['count' => null], Record::class);
verify('P06', $optional instanceof Record && $optional->count === null && $result->data->count === null, 'Nullable constructor default accepts both missing and explicit null.');
$error = hydrationError(['id' => '123'], StrictRecord::class);
verify('P07', $error->reason === 'strict_integer_expected', 'Existing explicit property Cast can reject scalar coercion.');
$strictNull = Hydrator::default()->hydrate(['id' => 123, 'count' => null], StrictRecord::class);
verify('P08', $strictNull instanceof StrictRecord && $strictNull->count === null, 'Null bypasses the property Cast; it cannot reject present null here.');
$error = hydrationError(array_replace($valid, ['id' => (string) PHP_INT_MAX.'0']), Record::class);
verify('P09', $error->reason === 'integer_out_of_range', 'Integer overflow already produces a structured hydration error.');

$known = ['type' => 'known'] + $valid;
$unknown = ['type' => 'future', 'payload' => ['enabled' => false]];
$list = execute(new ReadList(), ['data' => ['batch_id' => 7, 'items' => [$known, $unknown]]]);
verify('P10', $list->isSuccess() && $list->data instanceof RecordList && $list->data->batchId === 7 && $list->data->items[0] instanceof Record && $list->data->items[1] === $unknown, 'From and Nested discriminator work; unknown variants remain raw arrays.');
$bad = execute(new ReadList(), ['data' => ['batch_id' => 7, 'items' => [array_replace($known, ['id' => []])]]]);
verify('P11', ! $bad->isSuccess() && $bad->exception instanceof HydrationException && $bad->exception->path === 'data.items[0].id' && $bad->response?->status === 200, 'Malformed known variants fail with a nested path and preserved HTTP response.');
$renamed = execute(new ReadList(), ['data' => ['batch_id' => [], 'items' => []]]);
verify('P12', $renamed->exception instanceof HydrationException && $renamed->exception->path === 'data.batchId', 'Diagnostic path uses DTO property name, not the mapped source key batch_id.');
$associative = execute(new ReadList(), ['data' => ['batch_id' => 7, 'items' => ['key' => $known]]]);
verify('P13', $associative->isSuccess() && $associative->data instanceof RecordList && array_is_list($associative->data->items), 'Associative collection input is accepted and reindexed by Nested.');

$noEnvelope = execute(new ReadRecord(), $valid);
verify('P14', ! $noEnvelope->isSuccess() && $noEnvelope->exception instanceof HydrationException && $noEnvelope->exception->reason === 'unwrap_path_missing', 'Missing unwrap path does not fall back to the root payload.');
$nullEnvelope = execute(new ReadRecord(), ['data' => null]);
verify('P15', ! $nullEnvelope->isSuccess() && $nullEnvelope->exception instanceof HydrationException && $nullEnvelope->exception->reason === 'unexpected_response_shape', 'Null instead of the declared response DTO is rejected.');
$invalidJson = execute(new ReadRecord(), '{broken');
verify('P16', ! $invalidJson->isSuccess() && $invalidJson->response?->status === 200, 'Malformed JSON preserves the successful HTTP status for outcome classification.');

$http = new RecordingHttpClient([new Response(200, ['Content-Type' => 'application/json'], '{}')]);
$wireClient = client($http, new ClientConfig(
    baseUrl: 'https://example.invalid',
    containerProvider: new NullContainerProvider(),
    textBooleanFormat: BooleanFormat::Literal,
    wireBodySerializationPolicy: new DtoSerializationPolicy(serializeNulls: true),
));
$wire = $wireClient->send(new WriteRecord())->raw();
$request = $http->requests[0];
verify('P17', $wire->isSuccess() && $request->getUri()->getQuery() === 'preview=false', 'Literal boolean query and omitted nullable recipient work through native declarations.');
verify('P18', json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR) === ['title' => '0', 'active' => false, 'link' => null], 'Declarative Body preserves false, string zero and explicit null with wire policy.');

$fakeHttp = new RecordingHttpClient([]);
$fake = client($fakeHttp);
$fake->fake([ReadRecord::class => MockResponse::success(['data' => $valid])]);
$fake->preventStrayRequests();
$fakeResult = $fake->send(new ReadRecord())->raw();
verify('P19', $fakeResult->isSuccess() && $fakeResult->data instanceof Record && $fakeHttp->requests === [], 'Native fake hydrates DTOs but bypasses the real HttpTransport/PSR-18 boundary.');

// P20 зависит от отсутствующего адаптера внешнего SDK и здесь не воспроизводится.

$http = new RecordingHttpClient(array_fill(0, 2, new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => $valid], JSON_THROW_ON_ERROR))));
$limited = client($http, new ClientConfig(
    baseUrl: 'https://example.invalid',
    containerProvider: new NullContainerProvider(),
    rateLimit: new RateLimitConfig(limit: 2, period: 60, behavior: RateLimitBehavior::Throw),
));
$first = $limited->send(new ReadLimited())->raw();
$blockedOperation = $limited->send(new ReadLimited())->raw();
$remainingGlobal = $limited->send(new ReadRecord())->raw();
$blockedGlobal = $limited->send(new ReadRecord())->raw();
verify('P21', $first->isSuccess() && ! $blockedOperation->isSuccess() && $remainingGlobal->isSuccess() && ! $blockedGlobal->isSuccess() && count($http->requests) === 2, 'Client and operation quotas compose locally; operation rejection does not consume the remaining client permit.');

echo json_encode([
    'php' => PHP_VERSION,
    'source' => '.workflow/issue/iss-002-apisutra-reuse/artifacts/probe.php',
    'skipped' => ['P20' => 'External SDK executor source is not part of this repository'],
    'checks' => count($observations),
    'observations' => $observations,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
