<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Diagnostics\RedactionPolicy;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Exceptions\Testing\RecordingException;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\Transport\RecordingTransport;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;

$checkout = $argv[1] ?? '';
require $checkout . '/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    $prefix = 'Brahmic\\ApiSutra\\Tests\\Stubs\\';
    if (str_starts_with($class, $prefix)) {
        $path = dirname(__DIR__) . '/Stubs/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});
if (class_exists('Illuminate\Container\Container')) {
    throw new RuntimeException('Standalone окружение содержит Laravel');
}
$transport = new MockTransport();
$transport->fake(['*' => MockResponse::success(['data' => [1], 'meta' => ['has_more' => true, 'next_cursor' => 'same']])]);
$client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
$result = (new PaginatedRequest())->setClient($client)->paginate()->all();
if (!$result->isPartial() || $result->errors->first()?->context['reason'] !== 'pagination_stalled' || count($transport->getRecorded()) !== 2) {
    throw new RuntimeException('Standalone pagination guard не сработал');
}
$context = (new RedactionPolicy())->context(['body' => str_repeat('x', 65537)]);
if ($context['body'] !== null || $context['bodySize'] !== 65537) {
    throw new RuntimeException('Standalone safe limit не сработал');
}
$path = tempnam(sys_get_temp_dir(), 'apisutra-recording-smoke-');
try {
    try {
        (new RecordingTransport($transport, $path))->send(new PreparedRequest(HttpMethod::GET, 'https://fixture.test'));
        throw new RuntimeException('Recorder пропустил ошибку каталога');
    } catch (RecordingException $exception) {
        if ($exception->response->status !== 200) {
            throw new RuntimeException('Recorder потерял ответ');
        }
    }
} finally {
    unlink($path);
}
echo "Standalone contracts: pagination, safe diagnostics, recorder — OK.\n";
