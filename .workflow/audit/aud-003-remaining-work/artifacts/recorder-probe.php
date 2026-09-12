<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\RemainingWork;

use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\Transport\RecordingTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

$path = tempnam(sys_get_temp_dir(), 'apisutra-record-probe-');
$warnings = [];
set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
    $warnings[] = $severity;
    return true;
});
try {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $response = (new RecordingTransport($transport, $path))->send(new PreparedRequest(HttpMethod::GET, 'https://fixture.test'));
    echo json_encode(['case' => 'record_write_failure', 'responseStatus' => $response->status, 'warnings' => count($warnings), 'recordedFixture' => false], JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    restore_error_handler();
    unlink($path);
}
$directory = sys_get_temp_dir() . '/apisutra-record-probe-' . bin2hex(random_bytes(5));
try {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make("\xFF", 200, ['Content-Type' => 'application/octet-stream'])]);
    $response = (new RecordingTransport($transport, $directory))->send(new PreparedRequest(HttpMethod::GET, 'https://fixture.test'));
    echo json_encode(['case' => 'record_binary_response', 'responseStatus' => $response->status, 'fixtureBytes' => filesize($directory . '/request_1.json')], JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
}
