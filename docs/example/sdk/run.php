<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\AttributeExample\RecordDto;
use Example\Records\DemoClient;
use Example\Records\Resources\Records\Get\GetRecordRequest;

require __DIR__ . '/bootstrap.php';

$transport = new MockTransport();
$transport->preventStrayRequests();
$success = json_decode(
    (string) file_get_contents(__DIR__ . '/fixtures/record.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$failure = json_decode(
    (string) file_get_contents(__DIR__ . '/fixtures/error.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$transport->fake([
    GetRecordRequest::class => MockResponse::sequence([
        MockResponse::success($success),
        MockResponse::make($failure, 404),
    ]),
]);
$client = new DemoClient(ClientConfigFactory::create(), $transport);
$record = $client->records()->get(7)->send()->dataOrFail();
$error = $client->records()->get(404)->send()->resolved();

// Альтернативная атрибутная модель использует тот же исходный ответ.
$attributeRecord = RecordDto::from($success['data']);
if ($attributeRecord->id !== $record->id || $attributeRecord->title !== $record->title) {
    throw new RuntimeException('Атрибутная модель примера дала другой результат');
}

echo json_encode([
    'id' => $record->id,
    'title' => $record->title,
    'extra' => $record->_extra,
    'failed' => $error->isFailed(),
    'status' => $error->errorStatus(),
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
