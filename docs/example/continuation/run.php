<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Continuation\ContinuationAwaitOptions;
use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;
use Example\Continuation\FinalDto;
use Example\Continuation\PollRequest;
use Example\Continuation\StartRequest;
use Example\Continuation\TokenExtractor;
use Example\Records\DemoClient;

require __DIR__ . '/../sdk/bootstrap.php';

$transport = new MockTransport();
$transport->preventStrayRequests();
$pending = ['status' => 'pending', 'operationToken' => 'demo-job'];
$transport->fake([
    StartRequest::class => MockResponse::success($pending),
    PollRequest::class => MockResponse::sequence([
        MockResponse::success($pending),
        MockResponse::success(['status' => 'done', 'data' => ['id' => 7]]),
    ]),
]);
$rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
    ->withDto(FinalDto::class, DtoRules::create());
$client = new DemoClient(new ClientConfig(
    baseUrl: 'https://operations.example.test',
    continuationTokenExtractor: new TokenExtractor(),
    hydrationRules: $rules,
), $transport);
$handle = $client->send(new StartRequest());
$final = $handle->await(new ContinuationAwaitOptions(maxAttempts: 2, intervalMs: 0));
$again = $handle->await();

echo json_encode([
    'id' => $final->id,
    'cached_same_object' => $again === $final,
    'requests' => count($transport->getRecorded()),
], JSON_THROW_ON_ERROR) . PHP_EOL;
