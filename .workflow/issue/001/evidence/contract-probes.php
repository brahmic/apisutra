<?php

declare(strict_types=1);

namespace ApiSutraReview;

// Запуск: php contract-probes.php /path/to/apisutra-checkout
// Требуется composer install в проверяемом checkout. Сетевые запросы не выполняются.
$checkout = $argv[1] ?? '';
if (!is_file($checkout.'/vendor/autoload.php')) {
    fwrite(STDERR, "Укажите checkout ApiSutra с установленными dev-зависимостями.\n");
    exit(2);
}
require $checkout.'/vendor/autoload.php';

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Http\Post;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Attributes\Request\Query;
use Brahmic\ApiSutra\Auth\ApiKeyAuthenticator;
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\Request\RequestOptions;
use Brahmic\ApiSutra\Transport\HttpTransport;
use Brahmic\ApiSutra\Transport\RecordingTransport;
use Brahmic\ApiSutra\VO\Files\FileInput;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use DateInterval;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\SimpleCache\CacheInterface;

final class ReviewClient extends AbstractClient {}

#[Get('/profile')]
final class GetProfile extends AbstractRequest {}

#[Post('/resources')]
final class CreateResource extends AbstractRequest
{
    public function __construct(
        #[Query] public int $workspace_id = 10,
        #[Body] public string $title = 'Example',
        #[Body] public bool $enabled = false,
        #[Body] public array $labels = [],
    ) {}
}

#[Get('/resources')]
final class ListResources extends AbstractRequest
{
    public function __construct(#[Query] public bool $enabled = false, #[Query] public int $offset = 0) {}
}

#[Get('/resources?fixed=1')]
final class ExistingQuery extends AbstractRequest
{
    public function __construct(#[Query] public int $page = 2) {}
}

#[Get('/resources')]
final class SecretQuery extends AbstractRequest
{
    public function __construct(#[Query] public string $access_token = 'fixture-secret') {}
}

#[Post('/files')]
final class UploadFile extends AbstractRequest
{
    public function __construct(#[File] public FileInput $file) {}
}

// Только внутрипроцессное хранилище для коротких проб; TTL здесь не проверяется.
final class MemoryCache implements CacheInterface
{
    private array $data = [];
    public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool { $this->data[$key] = $value; return true; }
    public function delete(string $key): bool { unset($this->data[$key]); return true; }
    public function clear(): bool { $this->data = []; return true; }
    public function getMultiple(iterable $keys, mixed $default = null): iterable { foreach ($keys as $key) { yield $key => $this->get($key, $default); } }
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool { foreach ($values as $key => $value) { $this->set($key, $value, $ttl); } return true; }
    public function deleteMultiple(iterable $keys): bool { foreach ($keys as $key) { $this->delete($key); } return true; }
    public function has(string $key): bool { return array_key_exists($key, $this->data); }
}

final class AsyncSpy implements TransportInterface
{
    public int $syncCalls = 0;
    public int $asyncCalls = 0;
    public function send(PreparedRequest $request): ProviderResponse
    {
        $this->syncCalls++;
        return new ProviderResponse(200, ['Content-Type' => ['application/json']], '{}', $request, 0);
    }
    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        $this->asyncCalls++;
        return new FulfilledPromise(new ProviderResponse(200, ['Content-Type' => ['application/json']], '{}', $request, 0));
    }
}

function wire(): object
{
    return (object) ['requests' => [], 'options' => [], 'bodies' => [], 'statuses' => [200], 'networkFailures' => 0, 'body' => null];
}

function transport(object $wire): HttpTransport
{
    $http = new Client([
        'timeout' => 9,
        'connect_timeout' => 3,
        'handler' => static function (RequestInterface $request, array $options) use ($wire): FulfilledPromise {
            $wire->requests[] = $request;
            $wire->options[] = $options;
            // getContents(), в отличие от приведения stream к строке, не перематывает поток.
            $wire->bodies[] = $request->getBody()->getContents();
            if ($wire->networkFailures > 0) {
                $wire->networkFailures--;
                throw new ConnectException('Synthetic network failure', $request);
            }
            $status = count($wire->statuses) > 1 ? array_shift($wire->statuses) : $wire->statuses[0];
            $body = $wire->body ?? json_encode([
                'identity' => $request->getHeaderLine('Authorization'),
                'message' => 'Example response',
            ], JSON_THROW_ON_ERROR);
            return new FulfilledPromise(new Response($status, ['Content-Type' => 'application/json'], $body));
        },
    ]);
    $factory = new HttpFactory();
    return new HttpTransport($http, $factory, $factory);
}

function config(array $overrides = []): ClientConfig
{
    return new ClientConfig(...array_replace([
        'baseUrl' => 'https://api.example.test',
        'auth' => new ApiKeyAuthenticator('fixture-account-a', header: 'Authorization'),
        'timeout' => 77,
        'connectTimeout' => 11,
    ], $overrides));
}

function client(object $wire, array $overrides = []): ReviewClient
{
    return new ReviewClient(config($overrides), transport($wire));
}

function retry(): RetryConfig
{
    return new RetryConfig(attempts: 2, baseDelay: 0, maxDelay: 0, jitter: false);
}

$observations = [];
$errors = [];
function observe(string $id, string $topic, callable $probe): void
{
    global $observations, $errors;
    try {
        $observations[] = ['id' => $id, 'topic' => $topic, 'observed' => $probe()];
    } catch (\Throwable $exception) {
        $errors[] = ['id' => $id, 'exception' => $exception::class, 'message' => $exception->getMessage()];
    }
}

observe('R01', 'Timeout options', static function (): array {
    $wire = wire();
    (new GetProfile())->setClient(client($wire))->withTimeout(42, 7)->send();
    return ['requested' => [42, 7], 'actual' => [$wire->options[0]['timeout'], $wire->options[0]['connect_timeout']]];
});

observe('R02', 'Cache identity isolation', static function (): array {
    $wire = wire();
    $cache = new CacheConfig(store: new MemoryCache());
    $a = (new GetProfile())->setClient(client($wire, ['cache' => $cache]))->send()->dataOrFail();
    $b = (new GetProfile())->setClient(client($wire, ['cache' => $cache, 'auth' => new ApiKeyAuthenticator('fixture-account-b', header: 'Authorization')]))->send()->dataOrFail();
    return ['a' => $a['identity'], 'b' => $b['identity'], 'httpCalls' => count($wire->requests)];
});

observe('R03', 'Async pipeline dispatch', static function (): array {
    $spy = new AsyncSpy();
    (new GetProfile())->setClient(new ReviewClient(config(), $spy))->sendAsync()->rawAsync()->wait();
    return ['syncCalls' => $spy->syncCalls, 'asyncCalls' => $spy->asyncCalls];
});

observe('R04', 'PSR network exception retry', static function (): array {
    $wire = wire();
    $wire->networkFailures = 1;
    $result = (new GetProfile())->setClient(client($wire, ['retry' => retry()]))->send()->raw();
    return ['httpCalls' => count($wire->requests), 'success' => $result->isSuccess(), 'error' => $result->errors->first()?->code->value];
});

observe('R05', 'Final HTTP error after retries', static function (): array {
    $wire = wire();
    $wire->statuses = [503, 503];
    $result = (new GetProfile())->setClient(client($wire, ['retry' => retry()]))->send()->raw();
    return ['httpCalls' => count($wire->requests), 'responseStatus' => $result->response?->status, 'error' => $result->errors->first()?->code->value, 'message' => $result->errors->first()?->message];
});

observe('R06', 'Generic retry repeats state-changing request', static function (): array {
    $wire = wire();
    $wire->statuses = [503, 200];
    (new CreateResource())->setClient(client($wire, ['retry' => retry()]))->send();
    return ['httpCalls' => count($wire->requests)];
});

observe('R07', 'Query boolean representation', static function (): array {
    $wire = wire();
    (new ListResources())->setClient(client($wire))->send();
    return ['url' => (string) $wire->requests[0]->getUri()];
});

observe('R08', 'Existing URL query plus attributes', static function (): array {
    $wire = wire();
    (new ExistingQuery())->setClient(client($wire))->send();
    return ['url' => (string) $wire->requests[0]->getUri()];
});

observe('R09', 'Invalid UTF-8 request JSON', static function (): array {
    $wire = wire();
    $result = (new CreateResource(title: "\xB1"))->setClient(client($wire))->send()->raw();
    return ['httpCalls' => count($wire->requests), 'sentBody' => $wire->bodies[0] ?? null, 'success' => $result->isSuccess()];
});

observe('R10', 'Malformed JSON response', static function (): array {
    $wire = wire();
    $wire->body = '{broken';
    $result = (new GetProfile())->setClient(client($wire))->send()->raw();
    return ['success' => $result->isSuccess(), 'data' => $result->data];
});

observe('R11', 'Clearing auth scope', static function (): array {
    $options = RequestOptions::empty()->withAuthScope('secondary')->withoutAuth()->withAuth();
    $wire = wire();
    $client = client($wire, ['authScopes' => ['secondary' => new ApiKeyAuthenticator('fixture-account-b', header: 'Authorization')]]);
    (new GetProfile())->setClient($client)->withAuthScope('secondary')->withoutAuth()->withAuth()->send();
    return ['scope' => $options->getAuthScopeOverride(), 'authorization' => $wire->requests[0]->getHeaderLine('Authorization')];
});

observe('R12', 'Debug URL redaction', static function (): array {
    $wire = wire();
    $result = (new SecretQuery())->setClient(client($wire, ['debug' => true]))->send()->raw();
    $debug = $result->requestDebug();
    return ['url' => $debug['url'] ?? null, 'query' => $debug['query'] ?? null];
});

observe('R13', 'Recording transport default redaction', static function (): array {
    $wire = wire();
    $directory = sys_get_temp_dir().'/apisutra-fixture-probe-'.bin2hex(random_bytes(6));
    $client = new ReviewClient(config(), new RecordingTransport(transport($wire), $directory));
    (new GetProfile())->setClient($client)->send();
    $files = glob($directory.'/*.json');
    $record = json_decode(file_get_contents($files[0]), true, 512, JSON_THROW_ON_ERROR);
    foreach ($files as $file) { unlink($file); }
    rmdir($directory);
    return ['recordedAuthorization' => $record['request']['headers']['Authorization'] ?? null];
});

observe('R14', 'Stream payload replay', static function (): array {
    $wire = wire();
    $wire->statuses = [503, 200];
    (new UploadFile(FileInput::fromContent('example-file-content', 'example.txt')))->setClient(client($wire, ['retry' => retry()]))->send();
    return ['bodyLengths' => array_map('strlen', $wire->bodies), 'identical' => count($wire->bodies) === 2 && $wire->bodies[0] === $wire->bodies[1]];
});

observe('R15', 'Global cache on POST', static function (): array {
    $wire = wire();
    $client = client($wire, ['cache' => new CacheConfig(store: new MemoryCache())]);
    (new CreateResource())->setClient($client)->send();
    (new CreateResource())->setClient($client)->send();
    return ['httpCalls' => count($wire->requests)];
});

observe('R16', 'Separate query and JSON preserve false and empty list', static function (): array {
    $wire = wire();
    (new CreateResource())->setClient(client($wire))->send();
    return ['url' => (string) $wire->requests[0]->getUri(), 'body' => json_decode($wire->bodies[0], true, 512, JSON_THROW_ON_ERROR)];
});

observe('R17', 'Explicit cache scopes isolate identities', static function (): array {
    $wire = wire();
    $store = new MemoryCache();
    $a = (new GetProfile())->setClient(client($wire, ['cache' => new CacheConfig(store: $store, prefix: 'account:a:')]))->send()->dataOrFail();
    $b = (new GetProfile())->setClient(client($wire, ['cache' => new CacheConfig(store: $store, prefix: 'account:b:'), 'auth' => new ApiKeyAuthenticator('fixture-account-b', header: 'Authorization')]))->send()->dataOrFail();
    return ['a' => $a['identity'], 'b' => $b['identity'], 'httpCalls' => count($wire->requests)];
});

echo json_encode([
    'php' => PHP_VERSION,
    'baseline' => '96e8c4071f2cc517cb39dfca36101671259ab317',
    'mode' => 'Observations only; exit 0 means probes executed, not that target requirements passed.',
    'observations' => $observations,
    'probeErrors' => $errors,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
exit($errors === [] ? 0 : 1);
