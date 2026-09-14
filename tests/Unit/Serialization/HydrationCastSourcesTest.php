<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Casts\UppercaseCast;
use Brahmic\ApiSutra\Tests\Stubs\Dto\CastAttributeDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\CastStringDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\ContractRequest;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\PlainValueDto;
use Brahmic\ApiSutra\Tests\Stubs\Hydration\UppercaseExtension;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;

it('изолирует global registry и гидрирует без Laravel в отдельном процессе', function (): void {
    $process = proc_open(
        [PHP_BINARY, dirname(__DIR__, 2) . '/Support/hydration-standalone.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    expect($process)->toBeResource();
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    expect(proc_close($process))->toBe(0)->and($errors)->toBe('')
        ->and(json_decode($output, true, flags: JSON_THROW_ON_ERROR))->toBe([
            'registered' => 'LOWER',
            'plain' => 'lower',
            'profile' => 'LOWER',
            'property' => 'LOWER',
            'nested' => 'Sample',
            'providerPath' => 'child.count',
            'illuminate' => [],
        ]);
});

it('сохраняет аргумент casts конструктора Hydrator без влияния на plain DTO', function (): void {
    $casts = new CastRegistry();
    $casts->register('string', new UppercaseCast());
    $hydrator = new Hydrator(casts: $casts);

    expect($casts->get('string')->hydrate('lower'))->toBe('LOWER')
        ->and($hydrator->hydrate(['value' => 'lower'], PlainValueDto::class)->value)->toBe('lower');
});

it('разделяет casts клиента и extension между запросом и гидратацией ответа', function (
    bool $extension,
    string $dtoType,
    string $expected,
): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{"value":"lower"}')]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test',
        casts: $extension ? [] : ['string' => UppercaseCast::class],
        extensions: $extension ? [new UppercaseExtension()] : [],
    ), $transport);

    $result = $client->send(new ContractRequest($dtoType, value: 'lower'))->raw();

    expect($result->isSuccess())->toBeTrue()->and($result->data->value)->toBe($expected)
        ->and(json_decode($transport->getRecorded()[0]->body, true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['value' => 'LOWER']);
})->with([false, true])->with([
    [PlainValueDto::class, 'lower'],
    [CastStringDto::class, 'LOWER'],
    [CastAttributeDto::class, 'LOWER'],
]);
