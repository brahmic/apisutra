<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\As07;

use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Casts\IntegerCast;
use Brahmic\ApiSutra\Casts\JsonCast;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Diagnostics\RedactionPolicy;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Support\ArrayPath;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\UnwrapResponseRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

// Только искусственные данные и локальный транспорт; исходники не изменяются.
final readonly class StringIdDto
{
    public function __construct(public string $id) {}
}

function report(string $case, array $facts): void
{
    echo json_encode(['case' => $case] + $facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

report('runtime', ['php' => PHP_VERSION, 'int_size' => PHP_INT_SIZE]);
$config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
foreach (['missing' => [], 'null' => ['data' => ['item' => null]]] as $case => $envelope) {
    $payload = $envelope + ['id' => 7, 'name' => 'outer-fixture'];
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success($payload)]);
    $client = new TestClient($config, $transport);
    $result = (new UnwrapResponseRequest())->setClient($client)->send()->raw();
    report('unwrap.' . $case, [
        'path_state' => ArrayPath::getByPathWithStatus($payload, 'data.item')->state->name,
        'success' => $result->isSuccess(),
        'outer_dto_returned' => $result->data instanceof SimpleResponseDto && $result->data->name === 'outer-fixture',
        'http_calls' => count($transport->getRecorded()),
    ]);
}

$literal = '9223372036854775808';
$body = '{"id":' . $literal . '}';
$response = new ProviderResponse(200, [], $body, new PreparedRequest(HttpMethod::GET, 'https://api.test'), 0);
$hydrator = new Hydrator(new CastRegistry());
$decoded = $response->jsonStrict();
$dto = $hydrator->hydrate($decoded, StringIdDto::class);
report('bigint.decode', [
    'strict_type' => get_debug_type($decoded['id']),
    'permissive_type' => get_debug_type($response->json('id')),
    'string_dto_id' => $dto->id,
    'exact' => $dto->id === $literal,
]);

foreach ([$literal, '-9223372036854775809'] as $input) {
    try {
        $dto = $hydrator->hydrate(['id' => $input, 'name' => 'fixture'], SimpleResponseDto::class);
        report('bigint.int_cast', ['input' => $input, 'dto_id' => $dto->id, 'integer_cast' => (new IntegerCast())->hydrate($input)]);
    } catch (HydrationException $exception) {
        report('bigint.int_cast', ['input' => $input, 'rejected' => true, 'reason' => $exception->reason]);
    }
}

$transport = new MockTransport();
$transport->fake(['*' => MockResponse::make($body)]);
$result = (new CacheProbeRequest())->setClient(new TestClient($config, $transport))->send()->raw();
report('bigint.pipeline', ['success' => $result->isSuccess(), 'id_type' => get_debug_type($result->data['id'])]);
report('bigint.adjacent', [
    'json_cast_type' => get_debug_type((new JsonCast())->hydrate($body)['id']),
    'redacted_body' => (new RedactionPolicy())->body($body, 'application/json'),
]);
