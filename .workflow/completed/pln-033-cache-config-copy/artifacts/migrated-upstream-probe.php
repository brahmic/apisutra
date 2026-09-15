<?php

declare(strict_types=1);

$originalArtifacts = dirname(__DIR__, 3) . '/issue/iss-003-apisutra-contracts/artifacts';
require $originalArtifacts . '/bootstrap.php';

require $originalArtifacts . '/fixtures/Store.php';
require $originalArtifacts . '/fixtures/AppProvider.php';
require $originalArtifacts . '/fixtures/ProbeRequest.php';
require $originalArtifacts . '/fixtures/AuditClient.php';
require $originalArtifacts . '/fixtures/AuditHttpClient.php';
require $originalArtifacts . '/fixtures/ConstructorOwned.php';
require $originalArtifacts . '/fixtures/OwnedEnvelope.php';
require $originalArtifacts . '/fixtures/ReadOwned.php';

use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\FieldRule;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Brahmic\ApiSutra\Serialization\Rules\ValueShape;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Brahmic\ApiSutra\Transport\HttpTransport;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

function makeClient(ClientConfig $config, array $responses): array
{
    $http = new AuditHttpClient($responses);
    $factory = new HttpFactory;

    return [new AuditClient($config, new HttpTransport($http, $factory, $factory)), $http];
}

function repeatGet(ClientConfig $config): array
{
    [$client, $http] = makeClient($config, [new Response(200, [], '{"n":1}'), new Response(200, [], '{"n":2}')]);
    $first = $client->send(new ProbeRequest)->raw();
    $second = $client->send(new ProbeRequest)->raw();

    return ['httpCalls' => count($http->requests), 'first' => $first->data, 'second' => $second->data];
}

$base = new ClientConfig(baseUrl: 'https://example.invalid', containerProvider: new NullContainerProvider);
observe('with copies config; does not change existing client', function () use ($base) {
    [$client] = makeClient($base, []);
    $copy = $base->with(timeout: 7);

    return ['base' => $base->timeout, 'copy' => $copy->timeout, 'existingClient' => $client->getConfig()->timeout, 'sameProvider' => $base->containerProvider === $copy->containerProvider];
});
observe('with replaces whole nested RetryConfig', function () use ($base) {
    $original = $base->with(retry: new RetryConfig(attempts: 1, totalTimeoutMs: 900));
    $copy = $original->with(retry: new RetryConfig(baseDelay: 200));

    return ['originalAttempts' => $original->retry->attempts, 'newAttempts' => $copy->retry->attempts, 'originalBudget' => $original->retry->totalTimeoutMs, 'newBudget' => $copy->retry->totalTimeoutMs];
});
observe('fromLaravel reads only debug and environment automatically', function () {
    $provider = new AppProvider;
    $config = ClientConfig::fromLaravel(['baseUrl' => 'https://example.invalid', 'containerProvider' => $provider]);

    return ['calls' => $provider->calls, 'debug' => $config->debug, 'environment' => $config->environment->value, 'logger' => $config->logger, 'cache' => $config->cacheStore, 'auth' => $config->auth];
});
observe('fromLaravel explicit overrides avoid lookups', function () {
    $provider = new AppProvider;
    $config = ClientConfig::fromLaravel(['baseUrl' => 'https://example.invalid', 'containerProvider' => $provider, 'debug' => false, 'environment' => Environment::Production]);

    return ['calls' => $provider->calls, 'debug' => $config->debug, 'environment' => $config->environment->value];
});
observe('cache baseline', fn () => repeatGet($base->with(cacheStore: new Store, cacheConfig: new CacheConfig(ttl: 60))));
observe('with cacheConfig replacement', function () use ($base) {
    $old = new CacheConfig(ttl: 60);
    $replacement = new CacheConfig(ttl: 10);
    $config = $base->with(cacheStore: new Store, cacheConfig: $old)->with(cacheConfig: $replacement);

    return ['retainedOldConfig' => $config->cacheConfig === $old, 'ttl' => $config->cacheConfig->ttl];
});
observe('with cache null leaves cacheConfig effective', function () use ($base) {
    $config = $base->with(cacheStore: new Store, cacheConfig: new CacheConfig(ttl: 60))->with(cacheStore: null);

    return ['cacheIsNull' => $config->cacheStore === null, 'cacheConfigIsNull' => $config->cacheConfig === null, 'execution' => repeatGet($config)];
});
observe('with both cache entries null disables', fn () => repeatGet($base->with(cacheStore: new Store, cacheConfig: new CacheConfig())->with(cacheStore: null, cacheConfig: null)));
observe('with cache replacement works', function () use ($base) {
    $replacement = new CacheConfig(ttl: 10);
    $config = $base->with(cacheStore: new Store, cacheConfig: new CacheConfig(ttl: 60))->with(cacheStore: new Store, cacheConfig: $replacement);

    return ['usesReplacement' => $config->cacheConfig === $replacement, 'ttl' => $config->cacheConfig->ttl];
});
foreach ([null, new RetryConfig(attempts: 1), new RetryConfig(attempts: 2, baseDelay: 0, maxDelay: 0, jitter: false)] as $retry) {
    observe('retry attempts '.($retry?->attempts ?? 'null'), function () use ($base, $retry) {
        [$client, $http] = makeClient($base->with(retry: $retry), [new Response(503, [], '{"n":1}'), new Response(200, [], '{"n":2}')]);
        $result = $client->send(new ProbeRequest)->raw();

        return ['httpCalls' => count($http->requests), 'status' => $result->response?->status];
    });
}

observe('with unrelated option loses separately supplied cache store', function () use ($base) {
    $store = new Store;
    $config = $base->with(cacheStore: $store, cacheConfig: new CacheConfig(ttl: 60));
    $before = repeatGet($config);
    $store->clear();
    $copy = $config->with(timeout: 7);

    return ['originalUsesStore' => $config->cacheStore === $store, 'copyUsesStore' => $copy->cacheStore === $store, 'before' => $before, 'after' => repeatGet($copy)];
});
observe('with no overrides also loses separately supplied cache store', function () use ($base) {
    $store = new Store;
    $config = $base->with(cacheStore: $store, cacheConfig: new CacheConfig(ttl: 60));
    $copy = $config->with();

    return ['originalUsesStore' => $config->cacheStore === $store, 'copyUsesStore' => $copy->cacheStore === $store, 'execution' => repeatGet($copy)];
});

$rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
    ->withDto(ConstructorOwned::class, DtoRules::create()->extras('extra'))
    ->withDto(OwnedEnvelope::class, DtoRules::create()->field('items', FieldRule::create()->shape(ValueShape::list(ValueShape::dto(ConstructorOwned::class)))));
$hydrator = Hydrator::forRules($rules);

observe('constructor initializes scalar before property fallback', fn () => $hydrator->hydrate(['id' => 7, 'kind' => 'known'], ConstructorOwned::class));
observe('constructor initialized scalar inside list', fn () => $hydrator->hydrate(['items' => [['id' => 7, 'kind' => 'known']]], OwnedEnvelope::class));
observe('constructor initialized scalar through HTTP Returns', function () use ($base, $rules) {
    [$client, $http] = makeClient($base->with(hydrationRules: $rules), [new Response(200, [], '{"data":{"id":7,"kind":"known"}}')]);
    $result = $client->send(new ReadOwned)->raw();

    return ['success' => $result->isSuccess(), 'status' => $result->response?->status, 'exception' => $result->exception === null ? null : $result->exception::class, 'httpCalls' => count($http->requests)];
});
observe('constructor initialized scalar with conflicting source', fn () => $hydrator->hydrate(['id' => 7, 'kind' => 'other'], ConstructorOwned::class));
observe('constructor initialized scalar with missing source', fn () => $hydrator->hydrate(['id' => 7], ConstructorOwned::class));

report('U', 'upstream-observations.json');
