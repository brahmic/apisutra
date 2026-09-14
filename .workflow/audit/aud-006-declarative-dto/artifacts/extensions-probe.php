<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Exceptions\Serialization\HydrationException;
use Brahmic\ApiSutra\Serialization\Hydrator;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\AuditClient;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\AuditRequest;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\GuardedVariantListDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\MappedDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\MatrixDto;
use Brahmic\ApiSutra\Tests\Stubs\DtoFeedbackAudit\RawVariant;
use Brahmic\ApiSutra\Transport\MockTransport;

$loader = require dirname(__DIR__, 4) . '/vendor/autoload.php';
$loader->addPsr4('Brahmic\\ApiSutra\\Tests\\Stubs\\DtoFeedbackAudit\\', __DIR__ . '/Stubs');
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Illuminate\\')) {
        throw new RuntimeException('Проверка не должна загружать Illuminate: ' . $class);
    }
}, prepend: true);

$observations = [];

/** @param array<string, mixed> $expected */
$observe = static function (string $id, Closure $run, array $expected) use (&$observations): void {
    try {
        $actual = ['ok' => true, 'value' => $run()];
    } catch (HydrationException $error) {
        $actual = ['ok' => false, 'reason' => $error->reason, 'path' => $error->path];
    }
    $observations[] = compact('id', 'expected', 'actual') + ['matches' => $actual === $expected];
};

$hydrator = Hydrator::default();
$known = ['type' => 'known', 'record_id' => 7, 'active' => false];
$unknown = ['type' => 'future', 'enabled' => false, 'nothing' => null, 'zero' => 0, 'empty' => []];
$success = ['ok' => true, 'value' => true];
$shapeError = ['ok' => false, 'reason' => 'invalid_list_shape', 'path' => ''];

$observe('R01-provider-nested-raw', static function () use ($hydrator, $known, $unknown): bool {
    $dto = $hydrator->hydrate(['items' => [$known, $unknown]], GuardedVariantListDto::class);
    return $dto->items[0] instanceof MappedDto && $dto->items[0]->id === 7
        && $dto->items[1] instanceof RawVariant && $dto->items[1]->payload === $unknown;
}, $success);
$observe('R02-known-invalid', static fn () => $hydrator->hydrate(
    ['items' => [array_replace($known, ['record_id' => []])]], GuardedVariantListDto::class,
), ['ok' => false, 'reason' => 'invalid_field_type', 'path' => 'items[0].id']);
$observe('R03-associative-list', static fn () => $hydrator->hydrate(
    ['items' => ['key' => $known]], GuardedVariantListDto::class,
), $shapeError);
$observe('R04-sparse-list', static fn () => $hydrator->hydrate(
    ['items' => [1 => $known]], GuardedVariantListDto::class,
), $shapeError);
$observe('R05-empty-list', static fn () => $hydrator->hydrate(
    ['items' => []], GuardedVariantListDto::class,
)->items === [], $success);
$observe('R06-null-list', static fn () => $hydrator->hydrate(
    ['items' => null], GuardedVariantListDto::class,
), ['ok' => false, 'reason' => 'null_not_allowed', 'path' => 'items']);
$observe('R07-pipeline-shape-error', static function () use ($known): array {
    $client = new AuditClient(new ClientConfig(baseUrl: 'https://fixture.invalid', containerProvider: new NullContainerProvider()), new MockTransport());
    $client->fake([AuditRequest::class => MockResponse::success(['data' => ['items' => ['key' => $known]]])]);
    $client->preventStrayRequests();
    $result = $client->send(new AuditRequest(GuardedVariantListDto::class, 'data'))->raw();
    return [
        'success' => $result->isSuccess(), 'http' => $result->response?->status,
        'reason' => $result->exception instanceof HydrationException ? $result->exception->reason : null,
        'path' => $result->exception instanceof HydrationException ? $result->exception->path : null,
    ];
}, ['ok' => true, 'value' => ['success' => false, 'http' => 200, 'reason' => 'invalid_list_shape', 'path' => 'data']]);
$observe('R08-pipeline-known-and-raw', static function () use ($known, $unknown): bool {
    $client = new AuditClient(new ClientConfig(baseUrl: 'https://fixture.invalid', containerProvider: new NullContainerProvider()), new MockTransport());
    $client->fake([AuditRequest::class => MockResponse::success(['data' => ['items' => [$known, $unknown]]])]);
    $client->preventStrayRequests();
    $result = $client->send(new AuditRequest(GuardedVariantListDto::class, 'data'))->raw();
    return $result->isSuccess() && $result->data->items[0] instanceof MappedDto
        && $result->data->items[1] instanceof RawVariant && $result->data->items[1]->payload === $unknown;
}, $success);
$observe('R09-matrix-valid', static function () use ($hydrator): bool {
    $dto = $hydrator->hydrate(['rows' => [[['count' => 7]], [['count' => 8], ['count' => 9]]]], MatrixDto::class);
    return $dto->rows[0][0]->count === 7 && $dto->rows[1][0]->count === 8 && $dto->rows[1][1]->count === 9;
}, $success);
$observe('R10-matrix-inner-shape', static fn () => $hydrator->hydrate(
    ['rows' => [['key' => ['count' => 7]]]], MatrixDto::class,
), ['ok' => false, 'reason' => 'invalid_list_shape', 'path' => 'rows[0]']);
$observe('R11-matrix-invalid-item', static fn () => $hydrator->hydrate(
    ['rows' => [[['count' => []]]]], MatrixDto::class,
), ['ok' => false, 'reason' => 'invalid_field_type', 'path' => 'rows[0][0].count']);
$observe('R12-matrix-outer-shape', static fn () => $hydrator->hydrate(
    ['rows' => [1 => [['count' => 7]]]], MatrixDto::class,
), $shapeError);
$observe('R13-matrix-empty', static fn () => $hydrator->hydrate(['rows' => []], MatrixDto::class)->rows === []
    && $hydrator->hydrate(['rows' => [[]]], MatrixDto::class)->rows === [[]], $success);
$observe('R14-required-list', static fn () => $hydrator->hydrate([], GuardedVariantListDto::class),
    ['ok' => false, 'reason' => 'required_field_missing', 'path' => 'items']);

$mismatches = count(array_filter($observations, static fn (array $row): bool => !$row['matches']));
echo json_encode([
    'php' => PHP_VERSION, 'checks' => count($observations), 'mismatches' => $mismatches,
    'observations' => $observations,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
exit($mismatches === 0 ? 0 : 1);
