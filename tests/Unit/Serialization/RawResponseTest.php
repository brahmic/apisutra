<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use Brahmic\ApiSutra\Enums\Hooks\Hook;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use Brahmic\ApiSutra\Tests\Stubs\Extensions\TestResponseExtension;
use Brahmic\ApiSutra\Tests\Stubs\ProviderB\Requests\ProviderBDownloadRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CacheableRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PaginatedItemsRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\RawDtoRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\RawStringRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\ArrayCache;
use Brahmic\ApiSutra\Transport\HttpTransport;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

it('выбирает Auto или точное тело по runtime raw', function (?string $type, string $body, bool $raw, mixed $expected, ?string $error, int $status): void {
    $http = new SequenceHttpClient([new Response($status, $type === null ? [] : ['Content-Type' => $type], $body)]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 1)), new HttpTransport($http, $factory, $factory));
    $result = $client->send((new RetryPolicyRequest())->withRawResponse($raw))->raw();
    expect($result->data)->toBe($expected)->and($result->response->body)->toBe($body)
        ->and($result->errors->first()?->code->value)->toBe($error);
})->with([
    ['application/xml', '<x/>', false, '<x/>', null, 200],
    ['text/html', '<html/>', false, '<html/>', null, 200],
    ['application/xml', '{"ok":true}', false, '{"ok":true}', null, 200],
    [null, '<x/>', false, null, 'response_decoding_error', 200],
    [null, '<x/>', true, '<x/>', null, 200],
    ['application/json', '{', false, null, 'response_decoding_error', 200],
    ['application/json', '{', true, '{', null, 200],
    ['application/problem+json', '{"ok":true}', false, ['ok' => true], null, 200],
    ['application/json', 'null', false, null, null, 200],
    ['application/json', 'null', true, 'null', null, 200],
    ['application/json', '', true, '', null, 204],
    ['application/json', '', false, null, null, 204],
    ['text/html', '<html/>', true, null, 'rate_limited', 429],
    ['text/html', '<html/>', true, null, 'server_error', 500],
]);

it('сохраняет marker и сбрасывает nullable override без мутации запроса', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $request = (new RawStringRequest())->setClient($client);
    expect($request->send()->raw()->data)->toBe('{"ok":true}');
    $auto = $request->withRawResponse(false)->withHeader('X-Fixture', '1');
    expect($auto->send()->raw()->data)->toBe(['ok' => true])
        ->and($auto->withRawResponse(null)->send()->raw()->data)->toBe('{"ok":true}')
        ->and($auto->send()->raw()->data)->toBe(['ok' => true])
        ->and($request->send()->raw()->data)->toBe('{"ok":true}');
});

it('отклоняет конфликт raw до отправки', function (string $kind): void {
    $request = match ($kind) {
        'dto' => new RawDtoRequest(),
        'pagination' => new PaginatedItemsRequest(),
        'download' => new ProviderBDownloadRequest('fixture'),
        'target' => (new RetryPolicyRequest())->withDownloadTo('/not-created-by-test'),
    };
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $result = $client->send($request->withRawResponse())->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($transport->getRecorded())->toBe([]);
})->with(['dto', 'pagination', 'download', 'target']);

it('runtime Auto снимает конфликт marker, но не подменяет чужой формат пустым DTO', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('<x/>', 200, ['Content-Type' => 'application/xml'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $result = $client->send((new RawDtoRequest())->withRawResponse(false))->raw();
    expect($transport->getRecorded())->toHaveCount(1)
        ->and($result->errors->first()->code->value)->toBe('response_decoding_error')
        ->and($result->errors->first()->context['reason'])->toBe('unsupported_response_content_type');
});

it('raw обходит format handler и BeforeHydrate, сохраняя остальные hooks', function (): void {
    TestResponseExtension::reset();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', extensions: [new TestResponseExtension()]), $transport);
    $before = new class implements HookInterface {
        public int $calls = 0;
        public function handle(PipelineContext $context): ?array { $this->calls++; return null; }
    };
    $after = clone $before;
    $client->hooks()->on(Hook::BeforeHydrate, $before);
    $client->hooks()->on(Hook::AfterHydrate, $after);
    expect($client->send((new RetryPolicyRequest())->withRawResponse())->raw()->data)->toBe('{"ok":true}')
        ->and($before->calls)->toBe(0)->and($after->calls)->toBe(1);
    expect($client->send(new RetryPolicyRequest())->raw()->data)->toBe(['handled' => true, 'status' => 200]);
});

it('один cached HTTP ответ допускает Auto и Raw без новых ключей', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', cache: new CacheConfig(store: new ArrayCache())), $transport);
    $request = new CacheableRequest('fixture');
    expect($client->send($request)->raw()->data)->toBe(['ok' => true])
        ->and($client->send($request->withRawResponse())->raw()->data)->toBe('{"ok":true}')
        ->and($client->send($request)->raw()->data)->toBe(['ok' => true])
        ->and($transport->getRecorded())->toHaveCount(1);
});
