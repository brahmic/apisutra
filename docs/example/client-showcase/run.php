<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Auth\ApiKeyAuthenticator;
use Brahmic\ApiSutra\Auth\BearerAuthenticator;
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use Brahmic\ApiSutra\Exceptions\Request\RequestException;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\Serialization\Rules\DtoRules;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Serialization\Rules\RulePolicy;
use Brahmic\ApiSutra\Serialization\Rules\ScalarPolicy;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Example\ClientShowcase\DemoClient;
use Example\ClientShowcase\Resources\Records\Get\GetRecordRequest;
use Example\ClientShowcase\Resources\Records\RecordDto;
use Example\ClientShowcase\Resources\Records\Save\SaveRecordRequest;
use Example\ClientShowcase\Support\MemoryLogger;
use Example\ClientShowcase\Support\MemoryStore;
use Psr\Log\LogLevel;

require __DIR__ . '/../sdk/bootstrap.php';

$payload = ['data' => ['id' => 7, 'title' => 'Первая запись', 'future_flag' => true]];
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake([
    GetRecordRequest::class => MockResponse::success($payload),
    SaveRecordRequest::class => MockResponse::success(['saved' => true]),
]);

// 1. Базовое подключение: конфигурация и транспорт передаются отдельно.
$base = new ClientConfig(
    baseUrl: 'https://api.example.test',
    timeout: 15,
    connectTimeout: 3,
);
$client = new DemoClient($base, $transport);
$record = $client->records()->get(7)->send()->dataOrFail();
if (!$record instanceof RecordDto) {
    throw new RuntimeException('Ожидался типизированный RecordDto');
}
$prepared = $transport->getRecorded()[0];
$observed['connection'] = [
    'url' => $prepared->url,
    'type' => $record::class,
    'id' => $record->id,
    'timeoutMs' => $prepared->transportOptions?->timeoutMs,
    'connectTimeoutMs' => $prepared->transportOptions?->connectTimeoutMs,
];

// 2. Вымышленные credentials: основная стратегия и отдельный служебный scope.
$authenticated = $base->with(
    auth: new BearerAuthenticator('demo-user-token'),
    authScopes: ['service' => new ApiKeyAuthenticator('demo-service-key')],
);
$client = new DemoClient($authenticated, $transport);
$client->records()->get(7)->send()->dataOrFail();
$client->send($client->records()->get(7)->withAuthScope('service'))->dataOrFail();
$client->send($client->records()->get(7)->withoutAuth())->dataOrFail();
$requests = $transport->getRecorded();
$observed['auth'] = [
    'default' => $requests[1]->headers['Authorization'] ?? null,
    'service' => $requests[2]->headers['X-Api-Key'] ?? null,
    'serviceHasBearer' => isset($requests[2]->headers['Authorization']),
    'anonymousHasCredentials' => isset($requests[3]->headers['Authorization'])
        || isset($requests[3]->headers['X-Api-Key']),
];

// Альтернатива для API, ожидающего ключ в query, а не в заголовке.
$queryAuth = $base->with(auth: new ApiKeyAuthenticator('demo-query-key', header: null, query: 'api_key'));
(new DemoClient($queryAuth, $transport))->records()->get(7)->send()->dataOrFail();
$observed['auth']['queryUrl'] = $transport->getRecorded()[4]->url;

// 3. Фикстура временной ошибки: retry делает вторую попытку того же GET.
$retryTransport = new MockTransport();
$retryTransport->preventStrayRequests();
$retryTransport->fake([GetRecordRequest::class => MockResponse::sequence([
    MockResponse::make(['message' => 'Временно недоступно'], 503),
    MockResponse::success($payload),
])]);
$resilient = $base->with(
    retry: new RetryConfig(attempts: 2, baseDelay: 0, jitter: false),
);
$retryClient = new DemoClient($resilient, $retryTransport);
$retryResult = $retryClient->records()->get(7)->send()->raw();
$observed['retry'] = ['success' => $retryResult->isSuccess(), 'httpCalls' => count($retryTransport->getRecorded())];

// 4. Ограничение частоты проверяется отдельно, без кеша и повторов.
$before = count($transport->getRecorded());
$limited = $base->with(
    rateLimit: new RateLimitConfig(limit: 2, period: 60, behavior: RateLimitBehavior::Throw),
);
$limitedClient = new DemoClient($limited, $transport);
$limitedClient->records()->get(7)->send()->dataOrFail();
$limitedClient->records()->get(7)->send()->dataOrFail();
$limitedResult = $limitedClient->records()->get(7)->send()->raw();
$observed['quota'] = [
    'httpCalls' => count($transport->getRecorded()) - $before,
    'error' => $limitedResult->errors->first()?->code->value,
    'reason' => $limitedResult->errors->first()?->context['reason'] ?? null,
    'hasResponse' => $limitedResult->response !== null,
];

// 5. Кеш хранит ответ: повторный вызов получает новый DTO без нового HTTP.
$before = count($transport->getRecorded());
$store = new MemoryStore();
$cached = $base->with(cacheStore: $store, cacheConfig: new CacheConfig(ttl: 60))
    ->with(timeout: 7); // Копия сохраняет store и все параметры кеша.
$cachedClient = new DemoClient($cached, $transport);
$first = $cachedClient->records()->get(7)->send()->dataOrFail();
$second = $cachedClient->records()->get(7)->send()->dataOrFail();
$afterHit = count($transport->getRecorded()) - $before;
$cachedClient->send($cachedClient->records()->get(7)->withoutCache())->dataOrFail();
$afterBypass = count($transport->getRecorded()) - $before;
$cachedClient->clearCache();
$cachedClient->records()->get(7)->send()->dataOrFail();
$observed['cache'] = [
    'afterTwoReads' => $afterHit,
    'differentDtos' => $first !== $second,
    'afterBypass' => $afterBypass,
    'afterClear' => count($transport->getRecorded()) - $before,
];

// 6. Набор правил применяется к ответу и к исходящему представлению DTO.
$rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
    ->withDto(RecordDto::class, DtoRules::create()->extras('_extra'));
$typed = $base->with(hydrationRules: $rules);
$typedClient = new DemoClient($typed, $transport);
$record = $typedClient->records()->get(7)->send()->dataOrFail();
if (!$record instanceof RecordDto) {
    throw new RuntimeException('Правила должны сохранять тип RecordDto');
}
$typedClient->send(new SaveRecordRequest($record))->dataOrFail();
$requests = $transport->getRecorded();
$observed['dto'] = [
    'extras' => $record->_extra,
    'dx' => $record->toArray(),
    'wire' => json_decode((string) $requests[array_key_last($requests)]->body, true, flags: JSON_THROW_ON_ERROR),
];
$invalidTransport = new MockTransport();
$invalidTransport->preventStrayRequests();
$invalidTransport->fake([
    GetRecordRequest::class => MockResponse::success(['data' => ['id' => '7', 'title' => 'Неверный тип']]),
]);
$invalid = (new DemoClient($typed, $invalidTransport))->records()->get(7)->send()->raw();
$observed['dto']['invalidType'] = [
    'code' => $invalid->errors->first()?->code->value,
    'path' => $invalid->errors->first()?->context['path'] ?? null,
];

// 7. Явный trace и безопасный снимок запроса помогают разбирать результат.
$logger = new MemoryLogger();
$diagnostic = $authenticated->with(logger: $logger, logLevel: LogLevel::INFO, debug: true);
$diagnosticClient = new DemoClient($diagnostic, $transport);
$execution = $diagnosticClient->records()->get(7)->withTraceId('record-7');
$result = $diagnosticClient->send($execution)->raw();
$debug = $result->requestDebug();
$observed['diagnostics'] = [
    'trace' => $result->traceId,
    'debugAuthorization' => $debug['headers']['Authorization'] ?? null,
    'logged' => count($logger->records) > 0,
    'traceLogged' => in_array('record-7', array_column(array_column($logger->records, 'context'), 'trace'), true),
];
$errorTransport = new MockTransport();
$errorTransport->preventStrayRequests();
$errorTransport->fake([GetRecordRequest::class => MockResponse::notFound()]);
$errorClient = new DemoClient($base, $errorTransport);
$failed = $errorClient->records()->get(7)->send();
$observed['errors'] = ['code' => $failed->raw()->errors->first()?->code->value];
try {
    $failed->dataOrFail();
    $observed['errors']['dataOrFailThrows'] = false;
} catch (RequestException) {
    $observed['errors']['dataOrFailThrows'] = true;
}
$throwing = new DemoClient($base->with(throwOnErrors: true), $errorTransport);
try {
    $throwing->records()->get(7)->send();
    $observed['errors']['sendThrows'] = false;
} catch (RequestException) {
    $observed['errors']['sendThrows'] = true;
}

// 8. Копия конфигурации и разовые опции не меняют исходное подключение.
$before = count($transport->getRecorded());
$preview = $authenticated->with(baseUrl: 'https://preview.example.test', timeout: 4, auth: null);
$previewClient = new DemoClient($preview, $transport);
$previewClient->records()->get(7)->send()->dataOrFail();
$request = $client->records()->get(7);
$options = RequestOptions::empty()->withTimeout(2, connectTimeout: 1)->withoutAuth();
$client->send($request->withOptions($options))->dataOrFail();
$request->send()->dataOrFail();
$requests = array_slice($transport->getRecorded(), $before);
$observed['overrides'] = [
    'urls' => array_map(static fn (PreparedRequest $request): string => $request->url, $requests),
    'timeoutMs' => array_map(
        static fn (PreparedRequest $request): ?int => $request->transportOptions?->timeoutMs,
        $requests,
    ),
    'connectTimeoutMs' => array_map(
        static fn (PreparedRequest $request): ?int => $request->transportOptions?->connectTimeoutMs,
        $requests,
    ),
    'hasBearer' => array_map(
        static fn (PreparedRequest $request): bool => isset($request->headers['Authorization']),
        $requests,
    ),
    'baseTimeout' => $authenticated->timeout,
];

echo json_encode($observed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    . PHP_EOL;
