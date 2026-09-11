<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Attributes\Response\Returns;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\Core\AbstractRequest;
use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;

$root = $argv[1] ?? dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';

final class AuditRuntimeClient extends AbstractClient {}

final readonly class AuditRuntimeDto extends AbstractResponseDto
{
    public function __construct(public int $id) {}
}

#[Get('/item')]
#[Returns(AuditRuntimeDto::class)]
final class AuditRuntimeRequest extends AbstractRequest {}

$transport = new MockTransport();
$transport->fake([AuditRuntimeRequest::class => MockResponse::success(['id' => '42'])]);
$client = new AuditRuntimeClient(new ClientConfig('https://audit.invalid'), $transport);
$result = $client->send(new AuditRuntimeRequest())->raw();
echo json_encode([
    'illuminate_present' => class_exists('Illuminate\\Container\\Container'),
    'guzzle_http_client_present' => class_exists('GuzzleHttp\\Client'),
    'pest_present' => class_exists('Pest\\TestSuite'),
    'status' => $result->status->value,
    'dto_id' => $result->data?->id,
], JSON_THROW_ON_ERROR) . "\n";
