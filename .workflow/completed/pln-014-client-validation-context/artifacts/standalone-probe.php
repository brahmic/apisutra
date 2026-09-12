<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\ValidationProbe;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use Brahmic\ApiSutra\Tests\Stubs\Requests\CustomValidatableRequestStub;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Errors\ValidationError;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory;
use RuntimeException;

$checkout = $argv[1] ?? '';
if (!is_file($checkout . '/vendor/autoload.php')) {
    throw new RuntimeException('Укажите checkout с production dependencies (--no-dev) и текущими src');
}
require $checkout . '/vendor/autoload.php';
$root = dirname(__DIR__, 4);
foreach (['TestClient', 'Requests/CacheProbeRequest', 'Requests/CustomValidatableRequestStub'] as $stub) {
    require $root . '/tests/Stubs/' . $stub . '.php';
}
require __DIR__ . '/ProbeRequest.php';
require __DIR__ . '/ProbeDto.php';
if (interface_exists(Factory::class) || class_exists(Container::class)) {
    throw new RuntimeException('В checkout присутствует Illuminate');
}
ContainerProviderRegistry::reset();
foreach (['attributes' => new ProbeRequest(), 'no_attributes' => new CacheProbeRequest(), 'custom' => new CustomValidatableRequestStub()] as $case => $request) {
    if ($request instanceof CustomValidatableRequestStub) {
        $request->customValidationErrors = [new ValidationError('value', 'fixture_rule', 'fixture-rejected', null)];
    }
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $request->setClient(new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport));
    $result = $request->send()->raw();
    echo json_encode([
        'case' => $case, 'success' => $result->isSuccess(), 'error' => $result->errors->first()?->code->value,
        'http_calls' => count($transport->getRecorded()), 'illuminate_factory_available' => interface_exists(Factory::class),
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
}
echo json_encode(['case' => 'dto', 'valid' => (new ProbeDto())->isValid()], JSON_THROW_ON_ERROR) . PHP_EOL;
