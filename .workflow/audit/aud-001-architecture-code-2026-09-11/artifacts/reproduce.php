<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\DataTransfer\Validate;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Auth\BearerAuthenticator;
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Pipeline\Auth\AuthRefreshLock;
use Brahmic\ApiSutra\Serialization\SafeScalarHydrationCaster;
use Brahmic\ApiSutra\Tests\Support\ArrayCache;
use Brahmic\ApiSutra\Tests\Support\ControllableTimeCache;
use Brahmic\ApiSutra\Transport\HttpTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

use Brahmic\ApiSutra\Laravel\SdkServiceProvider;
use Brahmic\ApiSutra\Laravel\LaravelContainerProvider;
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactoryInterface;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Translation\Translator;
use Illuminate\Translation\ArrayLoader;
use Psr\SimpleCache\InvalidArgumentException as CacheInvalidArgumentException;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

final class AuditClient extends AbstractClient {}

#[Get('/account')]
final class AuditRequest extends AbstractRequest {}

#[Get('/account?id=1')]
final class AuditLiteralQueryA extends AbstractRequest {}

#[Get('/account?id=2')]
final class AuditLiteralQueryB extends AbstractRequest {}

#[Get('/account')]
final class AuditOrderedQuery extends AbstractRequest
{
    public function __construct(#[Query] public array $sort) {}
}

#[Get('/account')]
final class AuditSecretQuery extends AbstractRequest
{
    public function __construct(#[Query] public string $token) {}
}

#[Get('/account')]
final class AuditValidatedRequest extends AbstractRequest
{
    public function __construct(#[Validate('required')] public string $name = '') {}
}

final readonly class AuditIntegerDto extends AbstractResponseDto
{
    public function __construct(public int $id) {}
}

final readonly class AuditOptionalDto extends AbstractResponseDto
{
    public function __construct(public ?string $name = null) {}
}

#[Get('/account')]
#[Returns(AuditOptionalDto::class)]
final class AuditJsonRequest extends AbstractRequest {}

#[Get('/account')]
#[Returns(AuditIntegerDto::class)]
final class AuditIntegerRequest extends AbstractRequest {}

final class AuditTransport implements TransportInterface
{
    public int $syncCalls = 0;
    public int $asyncCalls = 0;
    public ?string $rawBodyOverride = null;

    public function send(PreparedRequest $request): ProviderResponse
    {
        $this->syncCalls++;
        return new ProviderResponse(200, [], $this->rawBodyOverride ?? json_encode([
            'url' => $request->url,
            'authorization' => $request->headers['Authorization'] ?? null,
            'call' => $this->syncCalls,
        ], JSON_THROW_ON_ERROR), $request, 0);
    }

    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        $this->asyncCalls++;
        return new FulfilledPromise($this->send($request));
    }
}

final class AuditCacheKeyException extends InvalidArgumentException implements CacheInvalidArgumentException {}

final class AuditStrictCache extends ArrayCache
{
    public function get(string $key, mixed $default = null): mixed
    {
        if (str_contains($key, ':')) {
            throw new AuditCacheKeyException('PSR-16 forbids colon: ' . $key);
        }
        return parent::get($key, $default);
    }
}

function emit(string $case, array $details): void
{
    echo json_encode(['case' => $case, ...$details], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}

$transport = new AuditTransport();
$cache = new ArrayCache();
$clientA = new AuditClient(new ClientConfig('https://audit.invalid', auth: new BearerAuthenticator('synthetic-A'), cacheConfig: new CacheConfig($cache)), $transport);
$clientB = new AuditClient(new ClientConfig('https://audit.invalid', auth: new BearerAuthenticator('synthetic-B'), cacheConfig: new CacheConfig($cache)), $transport);
$a = $clientA->send(new AuditRequest())->raw()->response?->json();
$b = $clientB->send(new AuditRequest())->raw()->response?->json();
emit('cache_auth_scope', ['first' => $a, 'second' => $b, 'transport_calls' => $transport->syncCalls]);

foreach (['literal_query', 'ordered_query'] as $case) {
    $transport = new AuditTransport();
    $client = new AuditClient(new ClientConfig('https://audit.invalid', cacheConfig: new CacheConfig(new ArrayCache())), $transport);
    $first = $case === 'literal_query' ? new AuditLiteralQueryA() : new AuditOrderedQuery(['name', 'date']);
    $second = $case === 'literal_query' ? new AuditLiteralQueryB() : new AuditOrderedQuery(['date', 'name']);
    $a = $client->send($first)->raw()->response?->json();
    $b = $client->send($second)->raw()->response?->json();
    emit($case, ['first' => $a, 'second' => $b, 'transport_calls' => $transport->syncCalls]);
}

$cache = new ControllableTimeCache(1000);
$transport = new AuditTransport();
$client = new AuditClient(new ClientConfig('https://audit.invalid', cacheConfig: new CacheConfig($cache, ttl: 10)), $transport);
$client->send(new AuditRequest())->raw();
$cache->advance(6);
$client->send(new AuditRequest())->raw();
$cache->advance(6);
$client->send(new AuditRequest())->raw();
emit('cache_ttl_renewed_on_read', ['elapsed_seconds' => 12, 'ttl_seconds' => 10, 'transport_calls' => $transport->syncCalls]);

foreach ([null, new ArrayCache()] as $store) {
    $lock = new AuthRefreshLock($store);
    $token = $lock->acquire('lock', 60);
    $lock->release('lock', $token);
    emit('refresh_lock_release', ['with_psr16_without_add' => $store !== null, 'reacquired' => $lock->acquire('lock', 60) !== null]);
}

$transport = new AuditTransport();
$client = new AuditClient(new ClientConfig('https://audit.invalid'), $transport);
$handle = $client->sendAsync(new AuditRequest());
emit('async_dispatch_before_wait', ['sync_calls' => $transport->syncCalls, 'async_calls' => $transport->asyncCalls, 'promise_state' => $handle->rawAsync()->getState()]);

$capturedOptions = [];
$http = new Client(['handler' => function (RequestInterface $request, array $options) use (&$capturedOptions): PromiseInterface {
    $capturedOptions = $options;
    return new FulfilledPromise(new Response(200, [], '{}'));
}]);
$factory = new HttpFactory();
$client = new AuditClient(new ClientConfig('https://audit.invalid', timeout: 1, connectTimeout: 2), new HttpTransport($http, $factory, $factory));
$client->send((new AuditRequest())->withTimeout(3, 4))->raw();
emit('transport_timeouts', ['requested_timeout' => 3, 'requested_connect_timeout' => 4, 'http_timeout' => $capturedOptions['timeout'] ?? null, 'http_connect_timeout' => $capturedOptions['connect_timeout'] ?? null]);

$attempts = 0;
$http = new Client(['handler' => function (RequestInterface $request, array $options) use (&$attempts): PromiseInterface {
    $attempts++;
    throw new ConnectException('Synthetic network failure', $request);
}]);
$client = new AuditClient(new ClientConfig('https://audit.invalid', retry: new RetryConfig(attempts: 3, baseDelay: 0, jitter: false)), new HttpTransport($http, $factory, $factory));
$result = $client->send(new AuditRequest())->raw();
emit('network_exception_retry', ['configured_attempts' => 3, 'actual_attempts' => $attempts, 'error_code' => $result->errors->first()?->code->value]);

$client = new AuditClient(new ClientConfig('https://audit.invalid', debug: true), new AuditTransport());
$snapshot = $client->send(new AuditSecretQuery('synthetic-secret'))->raw()->requestDebug();
emit('redacted_debug_url', ['url' => $snapshot['url'], 'query' => $snapshot['query']]);

$transport = new AuditTransport();
$client = new AuditClient(new ClientConfig('https://audit.invalid', rateLimit: new RateLimitConfig(limit: 10, period: 60, store: new AuditStrictCache())), $transport);
$result = $client->send(new AuditRequest())->raw();
emit('psr16_rate_limit_key', ['transport_calls' => $transport->syncCalls, 'error' => $result->errors->first()?->message]);

$value = (string) PHP_INT_MAX . '0';
$dto = AuditIntegerDto::from(['id' => $value]);
emit('integer_overflow_hydration', ['input' => $value, 'actual' => $dto->id]);

$caster = new SafeScalarHydrationCaster();
emit('float_overflow_hydration', ['input' => '1e1000', 'accepted' => $caster->canHydrate('float', '1e1000'), 'finite' => is_finite($caster->hydrate('float', '1e1000'))]);

$transport = new AuditTransport();
$transport->rawBodyOverride = '{broken-json';
$client = new AuditClient(new ClientConfig('https://audit.invalid'), $transport);
$result = $client->send(new AuditJsonRequest())->raw();
emit('invalid_json_with_declared_dto', ['status' => $result->status->value, 'data' => $result->data, 'has_error' => $result->errors->first() !== null]);

$app = new Container();
(new SdkServiceProvider($app))->register();
$config = new ClientConfig('https://audit.invalid');
try {
    $app->make(AuditClient::class);
    emit('quickstart_container_resolution', ['resolved' => true]);
} catch (Throwable $exception) {
    emit('quickstart_container_resolution', ['resolved' => false, 'error' => $exception->getMessage()]);
}

$factory = new ValidationFactory(new Translator(new ArrayLoader(), 'en'));
$app->instance(ValidationFactoryInterface::class, $factory);
$app->instance('validator', $factory);
$provider = new LaravelContainerProvider($app);
ContainerProviderRegistry::set(new NullContainerProvider());
$transport = new AuditTransport();
$client = new AuditClient(new ClientConfig('https://audit.invalid', containerProvider: $provider), $transport);
$first = $client->send(new AuditValidatedRequest())->raw();
ContainerProviderRegistry::set($provider);
$second = $client->send(new AuditValidatedRequest())->raw();
emit('client_container_validation', ['with_client_provider_only' => $first->status->value, 'with_global_provider' => $second->status->value, 'transport_calls' => $transport->syncCalls]);

$transport = new AuditTransport();
$transport->rawBodyOverride = '{"id":"not-an-integer"}';
$client = new AuditClient(new ClientConfig('https://audit.invalid'), $transport);
$result = $client->send(new AuditIntegerRequest())->raw();
emit('hydration_error_classification', ['http_status' => $result->response?->status, 'code' => $result->errors->first()?->code->value, 'message' => $result->errors->first()?->message]);
