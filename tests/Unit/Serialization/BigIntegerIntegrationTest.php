<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Hooks\Hook;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Testing\Fixture;
use Brahmic\ApiSutra\Testing\MockConfig;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Dto\CastAttributeDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\CastStringDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PaginationContainerDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\StringIdentifierDto;
use Brahmic\ApiSutra\Tests\Stubs\Extensions\TestResponseExtension;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\IndexedIdentifierRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\UnwrapPaginationRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\UnwrapResponseRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Support\StrictCache;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\Transport\RecordingTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

it('сохраняет bigint при JSON content type и повторном чтении из кеша', function (string $type): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{"id":9223372036854775808999}', headers: ['Content-Type' => $type])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', cacheStore: new StrictCache(), environment: Environment::Testing), $transport);
    $request = (new CacheProbeRequest())->setClient($client);
    expect($request->send()->dataOrFail())->toBe(['id' => '9223372036854775808999'])
        ->and($request->send()->dataOrFail())->toBe(['id' => '9223372036854775808999'])
        ->and($transport->getRecorded())->toHaveCount(1);
})->with(['application/json', 'application/problem+json', '']);

it('сохраняет числовой индекс unwrap и override DTO type', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{"data":[{"id":9223372036854775808999}]}')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing), $transport);
    $dto = (new IndexedIdentifierRequest())->setClient($client)->send()->dataOrFail();
    expect($dto)->toBeInstanceOf(StringIdentifierDto::class)->and($dto->id)->toBe('9223372036854775808999');
});

it('BeforeHydrate может явно нормализовать альтернативный envelope', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 7, 'name' => 'fixture'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing), $transport);
    $client->hooks()->on(Hook::BeforeHydrate, new class implements HookInterface {
        public function handle(PipelineContext $context): ?array
        {
            return ['data' => ['item' => $context->response->jsonStrict()]];
        }
    });
    $dto = (new UnwrapResponseRequest())->setClient($client)->send()->dataOrFail();
    expect($dto->id)->toBe(7)->and($dto->name)->toBe('fixture');
});

it('ResponseHandler сохраняет приоритет перед Returns unwrap', function (): void {
    TestResponseExtension::reset();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['data' => null])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing, extensions: [new TestResponseExtension()]), $transport);
    expect((new UnwrapResponseRequest())->setClient($client)->send()->dataOrFail())->toBe(['handled' => true, 'status' => 200]);
});

it('проверяет unwrap контейнера пагинации и сохраняет допустимый пустой DTO', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::sequence([MockResponse::success([]), MockResponse::success(['response' => []])])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing), $transport);
    $request = (new UnwrapPaginationRequest())->setClient($client);
    expect($request->send()->raw()->errors->first()->context)->toMatchArray(['reason' => 'unwrap_path_missing', 'path' => 'response']);
    expect($request->send()->dataOrFail())->toBeInstanceOf(PaginationContainerDto::class);
});

it('не меняет пользовательские casts атрибута и DTO профиля', function (): void {
    $hydrator = new Hydrator(new CastRegistry());
    foreach ([CastStringDto::class, CastAttributeDto::class] as $type) {
        $dto = $hydrator->hydrate(['value' => 'id:9223372036854775808999'], $type);
        expect($dto->value)->toBe('ID:9223372036854775808999');
    }
});

it('record и оба пути fixture playback сохраняют цифры и redaction', function (): void {
    $directory = sys_get_temp_dir() . '/apisutra-bigint-' . bin2hex(random_bytes(8));
    $prepared = new PreparedRequest(HttpMethod::GET, 'https://api.test', meta: ['requestClass' => CacheProbeRequest::class]);
    $body = '{"id":9223372036854775808999,"token":"fixture-secret"}';
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make($body)]);
    $previousPath = (new ReflectionProperty(MockConfig::class, 'fixturePath'))->getValue();
    try {
        $response = (new RecordingTransport($transport, $directory))->send($prepared);
        expect($response->body)->toBe($body);
        $files = glob($directory . '/*.json');
        $record = file_get_contents($files[0]);
        expect($record)->toContain('9223372036854775808999')->not->toContain('fixture-secret');
        $playback = new MockTransport();
        $playback->loadFixtures($directory);
        expect($playback->send($prepared)->json('id'))->toBe('9223372036854775808999');
        // Проверяем и внешнюю фикстуру с числовым литералом, не только наш строковый формат.
        $record = str_replace('"9223372036854775808999"', '9223372036854775808999', $record);
        file_put_contents($files[0], $record);
        $playback->loadFixtures($directory);
        expect($playback->send($prepared)->json('id'))->toBe('9223372036854775808999');
        MockConfig::setFixturePath($directory);
        $fixture = new class extends Fixture {
            protected function defineName(): string { return 'CacheProbeRequest_1'; }
        };
        $playback->fake(['*' => $fixture]);
        expect($playback->send($prepared)->json())->toBe(['id' => '9223372036854775808999', 'token' => '***']);
    } finally {
        (new ReflectionProperty(MockConfig::class, 'fixturePath'))->setValue(null, $previousPath);
        foreach (glob($directory . '/*.json') ?: [] as $file) { unlink($file); }
        if (is_dir($directory)) { rmdir($directory); }
    }
});
