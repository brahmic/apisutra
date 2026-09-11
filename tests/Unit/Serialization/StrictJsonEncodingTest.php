<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Casts\JsonCast;
use Brahmic\ApiSutra\Enums\Http\FileFormat;
use Brahmic\ApiSutra\Exceptions\Serialization\SerializationException;
use Brahmic\ApiSutra\Serialization\FilePayloadPreparer;

it('строго кодирует JSON-поля multipart и base64', function (FileFormat $format): void {
    expect(fn () => (new FilePayloadPreparer())->prepareBodyAndStream(
        $format, [], ['data' => ['invalid' => "\xB1"]], false, [],
    ))->toThrow(SerializationException::class);
})->with([FileFormat::Multipart, FileFormat::Base64]);

it('JsonCast не превращает ошибку JSON в false', function (): void {
    expect(fn () => (new JsonCast())->serialize(['value' => INF]))->toThrow(SerializationException::class)
        ->and((new JsonCast())->serialize(false))->toBe('false')
        ->and((new JsonCast())->serialize([]))->toBe('[]');
});
