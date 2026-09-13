<?php

declare(strict_types=1);

use ApiSutraAudit\BusinessRetryRequest;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Pipeline\Error\ErrorPolicy;
use Brahmic\ApiSutra\Retry\RetryAfterDelay;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use Brahmic\ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\VirtualClock;
use Brahmic\ApiSutra\Transport\HttpTransport;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
require __DIR__ . '/BusinessRetryRequest.php';

/** Проверки фиксируют исходное поведение; после исправления ожидаемые значения изменятся. */
function record(string $name, mixed $actual, mixed $expected): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($name . ': ' . json_encode($actual, JSON_THROW_ON_ERROR));
    }
    echo $name . ': ' . json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
}

// Реальный normalizer и pipeline, синтетические исключения Guzzle, без сетевого I/O.
$factory = new HttpFactory();
foreach ([55, 56, 52, 28] as $errno) {
    $psr = new Request('POST', 'https://fixture.test/retry-policy');
    $error = in_array($errno, [52, 28], true)
        ? new ConnectException('fixture', $psr, null, ['errno' => $errno])
        : new RequestException('fixture', $psr, null, null, ['errno' => $errno]);
    $http = new SequenceHttpClient([$error]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 1)), new HttpTransport($http, $factory, $factory));
    $result = $client->send(new RetryPolicyRequest(HttpMethod::POST))->raw();
    record('errno_' . $errno, $result->errors->first()?->code->value, match ($errno) {
        55, 56 => 'invalid_request', 52 => 'connection_failed', 28 => 'timeout',
    });
}

foreach ([
    ['text/html', '<html>fixture</html>', []],
    ['application/xml', '<x>fixture</x>', []],
    ['application/xml', '{"ok":true}', ['ok' => true]],
    [null, '<x>fixture</x>', null],
    [null, '{"ok":true}', ['ok' => true]],
    ['text/plain', 'fixture', 'fixture'],
] as $index => [$contentType, $body, $expected]) {
    $http = new SequenceHttpClient([new Response(200, $contentType === null ? [] : ['Content-Type' => $contentType], $body)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new HttpTransport($http, $factory, $factory));
    $result = $client->send(new RetryPolicyRequest())->raw();
    record('response_' . $index, [$result->data, $result->response?->body, $result->errors->first()?->code->value ?? null], [$expected, $body, $index === 3 ? 'response_decoding_error' : null]);
}

// Два независимых send получают по 1000 ms; повтор внутри send получает остаток.
foreach ([false, true] as $internalRetry) {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $timeouts = [];
    $transport->fake(['*' => static function () use ($clock, $transport, &$timeouts, $internalRetry): MockResponse {
        $sent = $transport->getRecorded();
        $timeouts[] = $sent[array_key_last($sent)]->transportOptions->effective()->timeoutMs;
        $clock->advance(600);
        return MockResponse::success($internalRetry && count($sent) === 1 ? ['error' => 'fixture.not.ready'] : ['ok' => true]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(
        attempts: 2, baseDelay: 0, jitter: false, totalTimeoutMs: 1000,
    )), $transport, $clock, $clock);
    $result = $client->send($internalRetry ? new BusinessRetryRequest() : new RetryPolicyRequest())->raw();
    if (!$internalRetry) {
        $result = $client->send(new RetryPolicyRequest())->raw();
    }
    record($internalRetry ? 'internal_retry' : 'independent_send', [$timeouts, $result->errors->first()?->code->value ?? null], [$internalRetry ? [1000, 400] : [1000, 1000], $internalRetry ? 'timeout' : null]);
}

// POST только после 429 уже выразим глобально, но ценой отключения network retry для всех методов.
foreach ([429, 503, 52] as $scenario) {
    $first = $scenario === 52
        ? new ConnectException('fixture', new Request('POST', 'https://fixture.test'), null, ['errno' => 52])
        : new Response($scenario, ['Content-Type' => 'application/json'], '{}');
    $http = new SequenceHttpClient([$first, new Response(200, ['Content-Type' => 'application/json'], '{}')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', authRetryOn401: false, retry: new RetryConfig(
        attempts: 2, baseDelay: 0, jitter: false, retryOn: [429], retryExceptions: [], safeMethods: [HttpMethod::POST],
    )), new HttpTransport($http, $factory, $factory));
    $client->send(new RetryPolicyRequest(HttpMethod::POST))->raw();
    record('post_only_429_' . $scenario, $http->calls, $scenario === 429 ? 2 : 1);
}

$now = 1_800_000_000;
$delay = new RetryAfterDelay(static fn (): int => $now);
foreach ([gmdate('D, d M Y H:i:s \G\M\T', $now + 120), '120', 'garbage', '-1'] as $header) {
    $response = new ProviderResponse(429, ['Retry-After' => [$header]], '{}', new PreparedRequest(HttpMethod::GET, 'https://fixture.test'), 0);
    $exception = (new ErrorPolicy())->getRequestExceptionInternal(new RetryPolicyRequest(), $response);
    $actual = [$exception->retryAfter, $delay->forResponse($response)];
    $expected = match ($header) {
        '120' => [120, 120000], 'garbage' => [0, 0], '-1' => [-1, 0], default => [0, 120000],
    };
    record('retry_after_' . $header, $actual, $expected);
}

echo "Все проверки исходного поведения пройдены.\n";
