<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\HydrationProbe;

use Brahmic\ApiSutra\Casts\JsonCast;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Dto\ConstructorDefaultDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\FallbackDefaultDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\IdentifierEnvelopeDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\InheritedNullablePayloadDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\InheritedRequiredPayloadDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\PolymorphicOwnersArrayKeyErrorDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\StrictFormatDateTimeDto;
use Brahmic\ApiSutra\Tests\Stubs\Dto\StrictFormatNullableDateTimeDto;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
require __DIR__ . '/RequiredNullableDto.php';
require __DIR__ . '/JsonPayloadDto.php';
require __DIR__ . '/ProbeRequest.php';

// Искусственные данные; проверка не выполняет реальный HTTP и не меняет код SDK.
$cases = [
    'required.missing' => [SimpleResponseDto::class, ['name' => 'fixture']],
    'required.null' => [SimpleResponseDto::class, ['id' => null, 'name' => 'fixture']],
    'required.array' => [SimpleResponseDto::class, ['id' => [], 'name' => 'fixture']],
    'nullable.required.missing' => [RequiredNullableDto::class, []],
    'nullable.required.null' => [RequiredNullableDto::class, ['note' => null]],
    'inherited.missing' => [InheritedRequiredPayloadDto::class, ['status' => 'active']],
    'inherited.nullable.missing' => [InheritedNullablePayloadDto::class, ['status' => 'active']],
    'nested.wrong_type' => [IdentifierEnvelopeDto::class, ['items' => [['id' => 1, 'name' => 'fixture'], ['id' => [], 'name' => 'fixture']]]],
    'nested.scalar' => [IdentifierEnvelopeDto::class, ['items' => [false]]],
    'json.malformed' => [JsonPayloadDto::class, ['payload' => '{"key":"fixture-secret"']],
    'json.empty' => [JsonPayloadDto::class, ['payload' => '']],
    'json.null' => [JsonPayloadDto::class, ['payload' => 'null']],
    'date.invalid' => [StrictFormatDateTimeDto::class, ['created_at' => 'fixture-secret']],
    'date.null_policy' => [StrictFormatNullableDateTimeDto::class, ['created_at' => 'fixture-secret']],
    'discriminator.unknown' => [PolymorphicOwnersArrayKeyErrorDto::class, ['owners' => [['fixture-secret' => ['id' => 1]]]]],
];
foreach ($cases as $case => [$type, $payload]) {
    $logger = new class extends AbstractLogger {
        /** @var list<string> */
        public array $entries = [];
        public function log(mixed $level, Stringable|string $message, array $context = []): void
        {
            $this->entries[] = (string) $message . json_encode($context, JSON_THROW_ON_ERROR);
        }
    };
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success($payload)]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', environment: Environment::Testing, logger: $logger, logLevel: LogLevel::ERROR,
    ), $transport);
    $result = (new ProbeRequest($type))->setClient($client)->send()->raw();
    $directException = null;
    try {
        $type::from($payload);
    } catch (Throwable $exception) {
        $directException = $exception::class;
    }
    echo json_encode([
        'case' => $case, 'success' => $result->isSuccess(),
        'error' => $result->errors->first()?->code->value,
        'path' => $result->errors->first()?->context['path'] ?? null,
        'exception' => $result->exception === null ? null : $result->exception::class,
        'direct_exception' => $directException,
        'payload_is_null' => $result->data instanceof JsonPayloadDto ? $result->data->payload === null : null,
        'message_leaks_fixture' => str_contains($result->errors->first()?->message ?? '', 'fixture-secret'),
        'log_leaks_fixture' => str_contains(implode('', $logger->entries), 'fixture-secret'),
        'http_status' => $result->response?->status, 'http_calls' => count($transport->getRecorded()),
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
}
echo json_encode([
    'case' => 'defaults.controls',
    'constructor_missing' => ConstructorDefaultDto::from([])->middleName,
    'constructor_null' => ConstructorDefaultDto::from(['middle_name' => null])->middleName,
    'fallback_null' => FallbackDefaultDto::from(['primary' => null, 'secondary' => 'fixture'])->name,
    'json_false' => (new JsonCast())->hydrate('false'),
    'json_bigint' => (new JsonCast())->hydrate('9223372036854775808999'),
], JSON_THROW_ON_ERROR) . PHP_EOL;
