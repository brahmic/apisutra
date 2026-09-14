<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\Pln031;

use Brahmic\ApiSutra\Attributes\AttributeMetadataCache;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Resolver\ClientRegistry;
use Brahmic\ApiSutra\Serialization\DtoSerializer;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Serializer;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\AttributeDefaultDto;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\BodyDtoRequest;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\CastDto;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\CastRequest;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\CompositeDefaultsRequest;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\DefaultsDto;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\DefaultsRequest;
use Brahmic\ApiSutra\Workflow\Pln031\Fixtures\PaginatedDefaultsRequest;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
foreach (glob(__DIR__ . '/Fixtures/*.php') as $fixture) {
    require $fixture;
}

/** @return array{TestClient, MockTransport} */
function client(?Environment $environment = null): array
{
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([
        DefaultsRequest::class => MockResponse::success(['value' => 'wire']),
        PaginatedDefaultsRequest::class => MockResponse::success(['data' => [[], []]]),
        CastRequest::class => MockResponse::success(['number' => 0]),
        BodyDtoRequest::class => MockResponse::success(['ok' => true]),
    ]);
    $config = new ClientConfig(baseUrl: 'https://probe.test');
    if ($environment !== null) {
        $config = $config->with(environment: $environment);
    }

    return [new TestClient($config, $transport), $transport];
}

function send(TestClient $client, AbstractRequest $request): mixed
{
    $request->setClient($client);
    $result = $client->send($request)->raw();
    $result->throw();

    return $result->data;
}

$checks = [];
$observations = [];
$checks['default_environment_production'] = (new ClientConfig(baseUrl: 'https://probe.test'))->environment === Environment::Production;

foreach ([false, true] as $enabled) {
    $label = $enabled ? 'cache_on' : 'cache_off';
    $cache = new AttributeMetadataCache($enabled);
    $hydrator = new Hydrator(new CastRegistry(), $cache);
    $a = $hydrator->hydrate([], DefaultsDto::class);
    $b = $hydrator->hydrate([], DefaultsDto::class);
    $observations[$label]['constructor_shared'] = $a->state === $b->state;
    $observations[$label]['nested_constructor_shared'] = $a->nested[0] === $b->nested[0];
    $checks[$label . '_constructor'] = $observations[$label]['constructor_shared'] === $enabled;
    $checks[$label . '_nested_constructor'] = $observations[$label]['nested_constructor_shared'] === $enabled;

    $a = $hydrator->hydrate([], AttributeDefaultDto::class);
    $b = $hydrator->hydrate([], AttributeDefaultDto::class);
    $counter = $a->states[0];
    $counter->value = 41;
    $observations[$label]['attribute_default_second'] = $b->states[0]->value;
    $checks[$label . '_attribute_default'] = $b->states[0]->value === ($enabled ? 41 : 0);

    $numbers = [];
    for ($i = 0; $i < 2; $i++) {
        $numbers[] = $hydrator->hydrate(['number' => 0], CastDto::class)->number;
    }
    $observations[$label]['hydrate_cast'] = $numbers;
    $checks[$label . '_hydrate_cast'] = $numbers === ($enabled ? [1, 2] : [1, 1]);

    $dtoSerializer = new DtoSerializer(new CastRegistry(), $cache);
    $numbers = [];
    for ($i = 0; $i < 2; $i++) {
        $numbers[] = $dtoSerializer->serialize(new CastDto())['number'];
    }
    $observations[$label]['dto_serialize_cast'] = $numbers;
    $checks[$label . '_dto_serialize_cast'] = $numbers === ($enabled ? [1, 2] : [1, 1]);

    // Отдельный кеш проверяет именно публичный Serializer и вложенный wire DTO.
    $serializer = new Serializer(new CastRegistry(), new AttributeMetadataCache($enabled));
    $queryNumbers = [];
    $bodyNumbers = [];
    for ($i = 0; $i < 2; $i++) {
        $request = new CastRequest();
        $context = new PipelineContext($request, new ClientConfig(baseUrl: 'https://probe.test'), 'synthetic');
        $prepared = $serializer->serialize($request, $context);
        parse_str(parse_url($prepared->url, PHP_URL_QUERY), $query);
        $queryNumbers[] = (int) $query['number'];
        $request = new BodyDtoRequest();
        $context = new PipelineContext($request, new ClientConfig(baseUrl: 'https://probe.test'), 'synthetic');
        $prepared = $serializer->serialize($request, $context);
        $bodyNumbers[] = json_decode($prepared->body, true, flags: JSON_THROW_ON_ERROR)['number'];
    }
    $observations[$label]['request_query_cast'] = $queryNumbers;
    $observations[$label]['wire_dto_cast'] = $bodyNumbers;
    $checks[$label . '_request_query_cast'] = $queryNumbers === ($enabled ? [1, 2] : [1, 1]);
    $checks[$label . '_wire_dto_cast'] = $bodyNumbers === ($enabled ? [1, 2] : [1, 1]);
}

foreach ([null, Environment::Local, Environment::Testing] as $environment) {
    [$sdk, $transport] = client($environment);
    $label = $environment?->name ?? 'DefaultProduction';
    $expectedShared = $environment === null;
    $a = send($sdk, new DefaultsRequest());
    $a->state->value = 73;
    $b = send($sdk, new DefaultsRequest());
    $observations[$label]['returns_second'] = $b->state->value;
    $checks[$label . '_returns'] = $b->state->value === ($expectedShared ? 73 : 0);
    $page = send($sdk, new PaginatedDefaultsRequest());
    $items = $page->items();
    $observations[$label]['pagination_items_shared'] = $items[0]->state === $items[1]->state;
    $observations[$label]['pagination_cross_returns'] = $items[0]->state->value;
    $checks[$label . '_pagination'] = ($items[0]->state === $items[1]->state) === $expectedShared
        && $items[0]->state->value === ($expectedShared ? 73 : 0);
    $aggregate = send($sdk, new CompositeDefaultsRequest());
    $observations[$label]['composite_state'] = $aggregate->state->value;
    $checks[$label . '_composite'] = $aggregate->value === 'aggregate'
        && $aggregate->state->value === ($expectedShared ? 73 : 0);
    $checks[$label . '_http_count'] = count($transport->getRecorded()) === 4;
}

$a = DefaultsDto::from([]);
$b = DefaultsDto::from([]);
$checks['dto_from_independent'] = $a->state !== $b->state && $a->nested[0] !== $b->nested[0];

[$sdk, $transport] = client();
$registry = new ClientRegistry();
$registry->register($sdk, 'Brahmic\\ApiSutra\\Workflow\\Pln031\\Fixtures');
$firstClient = $registry->resolve(DefaultsRequest::class);
$firstDto = send($firstClient, new DefaultsRequest());
$firstDto->state->value = 91;
unset($firstDto, $firstClient);
$secondClient = $registry->resolve(DefaultsRequest::class);
$secondDto = send($secondClient, new DefaultsRequest());
$observations['retained_client_second_task'] = $secondDto->state->value;
$checks['registry_retains_client_and_mutation'] = $secondClient === $sdk && $secondDto->state->value === 91;
[$separateClient] = client();
$separateDto = send($separateClient, new DefaultsRequest());
$checks['fresh_client_isolated'] = $separateDto->state->value === 0 && $separateDto->state !== $secondDto->state;

[$sdk, $transport] = client();
$numbers = [];
for ($i = 0; $i < 2; $i++) {
    $numbers[] = send($sdk, new CastRequest())->number;
    send($sdk, new BodyDtoRequest());
}
$wireQuery = [];
$wireBody = [];
foreach ($transport->getRecorded() as $prepared) {
    if ($prepared->body === null) {
        parse_str(parse_url($prepared->url, PHP_URL_QUERY), $query);
        $wireQuery[] = (int) $query['number'];
    } else {
        $wireBody[] = json_decode($prepared->body, true, flags: JSON_THROW_ON_ERROR)['number'];
    }
}
$observations['default_client_casts'] = ['hydrate' => $numbers, 'query' => $wireQuery, 'body' => $wireBody];
$checks['default_client_hydrate_cast'] = $numbers === [1, 2];
$checks['default_client_query_cast'] = $wireQuery === [1, 2];
$checks['default_client_body_cast'] = $wireBody === [1, 2];

echo json_encode(['checks' => $checks, 'observations' => $observations], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
exit(in_array(false, $checks, true) ? 1 : 0);
