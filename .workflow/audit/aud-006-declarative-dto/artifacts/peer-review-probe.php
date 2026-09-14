<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\DataTransfer\DefaultValue;
use Brahmic\ApiSutra\Casts\CastRegistry;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\AuditClient;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\AuditRequest;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\PlainGraphDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\PlainScalarDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\RecordingHttpClient;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\RequiredNullableDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ReviewCasesDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ReviewExtension;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ReviewNestedDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ReviewNestedFromDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ReviewNormalizedDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ReviewNullablePropertyDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ReviewNullableWithConstructorDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ReviewResponseHandler;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\ReviewScalarListDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\StrictScalarCast;
use Brahmic\ApiSutra\Transport\HttpTransport;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

$loader = require dirname(__DIR__, 4) . '/vendor/autoload.php';
$loader->addPsr4('Brahmic\\ApiSutra\\Tests\\Stubs\\DtoFeedbackAudit\\', __DIR__ . '/Stubs');
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Illuminate\\')) {
        throw new RuntimeException('Проверка не должна загружать Illuminate: ' . $class);
    }
}, prepend: true);

$observations = [];

/** Ожидания заданы вручную и фиксируют текущее поведение, включая дефекты. */
$probe = static function (string $id, Closure $run, array $expected) use (&$observations): void {
    try {
        $actual = ['ok' => true, 'value' => $run()];
    } catch (Throwable $error) {
        $actual = ['ok' => false, 'exception' => $error::class];
        if ($error instanceof HydrationException) {
            $actual += ['reason' => $error->reason, 'path' => $error->path];
        }
    }
    $observations[] = compact('id', 'expected', 'actual') + ['matches' => $expected === $actual];
};
$ok = static fn (mixed $value): array => ['ok' => true, 'value' => $value];
$error = static fn (string $reason, string $path): array => [
    'ok' => false, 'exception' => HydrationException::class, 'reason' => $reason, 'path' => $path,
];
$hydrator = Hydrator::default();

/** @param array<string, mixed> $payload
 * @param class-string $type
 * @param list<ReviewExtension> $extensions
 */
$http = static function (array $payload, string $type, array $extensions = []): ExecutionResult {
    $psr = new RecordingHttpClient([new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR))]);
    $factory = new HttpFactory();
    $client = new AuditClient(new ClientConfig(
        baseUrl: 'https://fixture.invalid', containerProvider: new NullContainerProvider(), extensions: $extensions,
    ), new HttpTransport($psr, $factory, $factory));
    $result = $client->send(new AuditRequest($type, 'data'))->raw();
    if (count($psr->requests) !== 1) {
        throw new RuntimeException('Ожидался один запрос к локальному PSR-18 стенду');
    }
    return $result;
};
$httpError = static fn (ExecutionResult $result): array => [
    'success' => $result->isSuccess(), 'status' => $result->response?->status,
    'exception' => $result->exception === null ? null : $result->exception::class,
    'reason' => $result->exception instanceof HydrationException ? $result->exception->reason : null,
    'path' => $result->exception instanceof HydrationException ? $result->exception->path : null,
];
$httpExpected = static fn (string $reason, string $path): array => $ok([
    'success' => false, 'status' => 200, 'exception' => HydrationException::class, 'reason' => $reason, 'path' => $path,
]);

$probe('C01-single-nested-array', static fn (): object => $hydrator->hydrate(['address' => ['city' => 'Sample']], ReviewNestedDto::class), $error('unexpected_response_shape', 'address[0]'));
$probe('C02-single-nested-object', static fn (): string => $hydrator->hydrate(['address' => (object) ['city' => 'Sample']], ReviewNestedDto::class)->address->city, $ok('Sample'));
$probe('C03-single-nested-http', static fn (): array => $httpError($http(['data' => ['address' => ['city' => 'Sample']]], ReviewNestedDto::class)), $httpExpected('unexpected_response_shape', 'data.address[0]'));
$probe('C04-single-nested-typeerror', static fn (): object => $hydrator->hydrate(['source' => ['wrapped' => ['count' => 7]]], ReviewNestedFromDto::class), ['ok' => false, 'exception' => TypeError::class]);
$probe('C05-single-nested-typeerror-http', static function () use ($http, $httpError): array {
    $result = $http(['data' => ['source' => ['wrapped' => ['count' => 7]]]], ReviewNestedFromDto::class);
    $previous = $result->exception?->getPrevious();
    return $httpError($result) + ['previous' => $previous === null ? null : $previous::class];
}, $ok([
    'success' => false, 'status' => 200, 'exception' => HydrationException::class, 'reason' => null, 'path' => null,
    'previous' => TypeError::class,
]));
$probe('C06-plain-child-native', static fn (): object => $hydrator->hydrate(['child' => ['count' => 7]], PlainGraphDto::class), $error('invalid_field_type', 'child'));
$probe('C07-plain-child-cast', static fn (): int => $hydrator->hydrate(['child' => ['count' => 7]], ReviewCasesDto::class)->child->count, $ok(7));
$probe('C08-plain-child-cast-error', static fn (): object => $hydrator->hydrate(['child' => ['count' => []]], ReviewCasesDto::class), $error('invalid_field_type', 'child.count'));

$probe('C09-scalar-list-profile', static fn (): array => $hydrator->hydrate(['ids' => ['1', 'x', null]], ReviewCasesDto::class)->ids, $ok(['1', 'x', null]));
$probe('C10-scalar-list-plain', static fn (): array => $hydrator->hydrate(['ids' => ['1', 'x', null]], ReviewScalarListDto::class)->ids, $ok(['1', 'x', null]));
$probe('C11-scalar-itemcast-valid', static fn (): array => $hydrator->hydrate(['strictIds' => [0, 7]], ReviewCasesDto::class)->strictIds, $ok([0, 7]));
$probe('C12-scalar-itemcast-string', static fn (): object => $hydrator->hydrate(['strictIds' => [7, '1']], ReviewCasesDto::class), $error('invalid_field_type', 'strictIds[1]'));
$probe('C13-scalar-itemcast-null', static fn (): object => $hydrator->hydrate(['strictIds' => [7, null]], ReviewCasesDto::class), $error('invalid_field_type', 'strictIds[1]'));
$probe('C14-itemcast-no-arguments', static fn (): object => $hydrator->hydrate(['parameterized' => [7]], ReviewCasesDto::class), ['ok' => false, 'exception' => ArgumentCountError::class]);

$probe('C15-child-provider-path', static fn (): object => $hydrator->hydrate(['guardedChild' => ['count' => null]], ReviewCasesDto::class), $error('explicit_null_not_allowed', 'guardedChild'));
$probe('C16-child-provider-http-path', static fn (): array => $httpError($http(['data' => ['guardedChild' => ['count' => null]]], ReviewCasesDto::class)), $httpExpected('explicit_null_not_allowed', 'data.guardedChild'));
$probe('C17-provider-default-keep', static fn (): string => $hydrator->hydrate(['keep' => ''], ReviewCasesDto::class)->keep, $ok('present'));
$probe('C18-provider-normalized', static fn (): string => $hydrator->hydrate(['normalized' => ''], ReviewCasesDto::class)->normalized, $ok('null'));
$probe('C19-cast-skips-normalization', static fn (): string => $hydrator->hydrate(['castSkip' => ''], ReviewCasesDto::class)->castSkip, $ok('present'));
$probe('C20-nullable-property', static fn (): mixed => $hydrator->hydrate([], ReviewNullablePropertyDto::class)->count, $ok(null));
$probe('C21-nullable-property-constructor', static fn (): mixed => $hydrator->hydrate([], ReviewNullableWithConstructorDto::class)->count, $ok(null));
$probe('C22-required-nullable-argument', static fn (): object => $hydrator->hydrate([], RequiredNullableDto::class), $error('required_field_missing', 'count'));
$probe('C23-combined-provider-null', static fn (): object => $hydrator->hydrate(['guardedIds' => null], ReviewCasesDto::class), $error('explicit_null_not_allowed', ''));
$probe('C24-combined-provider-list', static fn (): array => $hydrator->hydrate(['guardedIds' => [7]], ReviewCasesDto::class)->guardedIds, $ok([7]));
$probe('C25-combined-provider-missing', static fn (): mixed => $hydrator->hydrate([], ReviewCasesDto::class)->guardedIds, $ok(null));
$probe('C26-combined-provider-shape', static fn (): object => $hydrator->hydrate(['guardedIds' => ['key' => 7]], ReviewCasesDto::class), $error('invalid_list_shape', ''));
$probe('C27-combined-provider-item', static fn (): object => $hydrator->hydrate(['guardedIds' => [7, 'x']], ReviewCasesDto::class), $error('invalid_field_type', 'guardedIds[1]'));
$probe('C28-default-not-repeatable', static fn (): bool => ((new ReflectionClass(DefaultValue::class))->getAttributes(Attribute::class)[0]->newInstance()->flags & Attribute::IS_REPEATABLE) !== 0, $ok(false));
$probe('C29-global-registry-ignored', static function () use ($hydrator): int {
    CastRegistry::global()->register('int', new StrictScalarCast('int'));
    return $hydrator->hydrate(['count' => '7'], PlainScalarDto::class)->count;
}, $ok(7));
$probe('C30-extension-registry-ignored', static fn (): int => $http(['data' => ['count' => '7']], PlainScalarDto::class, [new ReviewExtension()])->data->count, $ok(7));
$probe('C31-handler-replaces-returns', static fn (): mixed => $http(['other' => []], ReviewNestedDto::class, [new ReviewExtension(new ReviewResponseHandler())])->data, $ok(['handled' => true]));
$probe('C32-handler-null-fallback', static fn (): array => $httpError($http(['other' => []], ReviewNestedDto::class, [new ReviewExtension(new ReviewResponseHandler(true))])), $httpExpected('unwrap_path_missing', 'data'));
$probe('C33-provider-nonkeep-policy', static fn (): string => $hydrator->hydrate(['value' => ''], ReviewNormalizedDto::class)->value, $ok('null'));
$probe('C34-parameterized-list-cast', static fn (): array => $hydrator->hydrate(['listCast' => [0, 7]], ReviewCasesDto::class)->listCast, $ok([0, 7]));
$probe('C35-parameterized-list-cast-error', static fn (): object => $hydrator->hydrate(['listCast' => [7, 'x']], ReviewCasesDto::class), $error('invalid_field_type', 'listCast[1]'));

$mismatches = count(array_filter($observations, static fn (array $row): bool => !$row['matches']));
echo json_encode(['php' => PHP_VERSION, 'checks' => count($observations), 'mismatches' => $mismatches, 'observations' => $observations], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
exit($mismatches === 0 ? 0 : 1);
