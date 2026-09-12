<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Core\AbstractClient;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;
use Generator;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

// Сохранённый генератор после break остаётся приостановленным, а не закрытым.
$state = (object) ['closed' => false];
$generator = (static function () use ($state): Generator {
    try {
        yield 1;
        yield 2;
    } finally {
        $state->closed = true;
    }
})();
foreach ($generator as $value) {
    break;
}
$closedAfterBreak = $state->closed;
unset($generator);
$closedAfterDestruction = $state->closed;
if ($closedAfterBreak || !$closedAfterDestruction) {
    throw new RuntimeException('Неожиданный срок жизни генератора');
}

$baseline = json_decode(file_get_contents(__DIR__ . '/baseline.json'), true, flags: JSON_THROW_ON_ERROR);
$changed = [];
foreach ($baseline['sha256'] as $path => $expected) {
    if (hash_file('sha256', dirname(__DIR__, 4) . '/' . $path) !== $expected) {
        $changed[] = $path;
    }
}

echo json_encode([
    'date' => '2026-09-13',
    'php' => PHP_VERSION,
    'client_config_parameters' => count((new ReflectionClass(ClientConfig::class))->getConstructor()->getParameters()),
    'context_constructor_public' => (new ReflectionClass(PipelineContext::class))->getConstructor()->isPublic(),
    'send_in_context_public' => (new ReflectionMethod(AbstractClient::class, 'sendInContext'))->isPublic(),
    'generator_closed_after_break' => $closedAfterBreak,
    'generator_closed_after_destruction' => $closedAfterDestruction,
    'baseline_files_checked' => count($baseline['sha256']),
    'baseline_files_changed' => $changed,
    'scope' => 'Language/lifecycle probe and source comparison; no implementation of planned controls',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
