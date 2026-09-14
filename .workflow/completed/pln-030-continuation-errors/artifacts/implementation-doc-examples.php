<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Continuation\ContinuationContext;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationStatus;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\Continuation\UndeclaredRequest;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Transport\MockTransport;

$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';

// Проверяем именно опубликованные фрагменты, не их отдельную копию.
foreach ([
    'docs/guides/provider-async-await.md' => ['OperationStateResolver', 'ProviderContinuationModeApplicator'],
    'docs/guides/continuation-token.md' => ['ProviderContinuationTokenExtractor'],
] as $path => $classes) {
    preg_match_all('/```php\n(.*?)```/s', file_get_contents($root . '/' . $path), $blocks);
    foreach ($classes as $class) {
        $found = false;
        foreach ($blocks[1] as $block) {
            if (str_contains($block, 'class ' . $class . ' ')) {
                // Глобальный use Override из примера не нужен в глобальной области probe.
                eval(str_replace("use Override;\n", '', $block));
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new LogicException('Фрагмент документации не найден: ' . $class);
        }
    }
}

$transport = new MockTransport();
$transport->preventStrayRequests();
$client = new TestClient(new ClientConfig(baseUrl: 'https://example.test'), $transport);
$resolver = new OperationStateResolver();
$extractor = new ProviderContinuationTokenExtractor();
$checks = [];
foreach (['done' => ContinuationStatus::Ready, 'failed' => ContinuationStatus::Failed, 'running' => ContinuationStatus::Pending] as $status => $expected) {
    $transport->fake([UndeclaredRequest::class => MockResponse::success([
        'status' => $status, 'data' => ['value' => 'fixture'], 'operationToken' => 'fixture-token',
    ])]);
    $result = $client->send(new UndeclaredRequest())->raw();
    $state = $resolver->resolve($result, new ContinuationContext(null, null, UndeclaredRequest::class, ContinuationMode::Auto));
    $checks[$status] = $state->status === $expected;
    $checks[$status . '-token'] = $extractor->extract($result) === 'fixture-token';
}
$withoutResponse = new ExecutionResult([], ResultStatus::SUCCESS, new ErrorCollection([]));
$checks['no-response'] = $resolver->resolve($withoutResponse, new ContinuationContext(null, null, null, ContinuationMode::Auto))->status === ContinuationStatus::Pending;
echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
exit(in_array(false, $checks, true) ? 1 : 0);
