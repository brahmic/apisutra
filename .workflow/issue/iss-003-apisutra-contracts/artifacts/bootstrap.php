<?php

declare(strict_types=1);

use Composer\InstalledVersions;

$arguments = array_values(array_filter(array_slice($argv, 1), fn (string $argument): bool => $argument !== '--verify'));
$autoload = $arguments[0] ?? dirname(__DIR__, 4).'/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "Pass the path to a Composer vendor/autoload.php.\n");
    exit(1);
}
require $autoload;

$observations = [];

function observe(string $name, callable $callback): void
{
    global $observations;

    try {
        $observations[] = ['case' => $name, 'result' => $callback()];
    } catch (Throwable $error) {
        $observations[] = [
            'case' => $name,
            'error' => $error::class,
            'message' => $error->getMessage(),
            'context' => method_exists($error, 'context') ? $error->context() : null,
        ];
    }
}

function report(string $prefix, string $snapshot): void
{
    global $observations, $argv;

    $records = [];
    foreach ($observations as $index => $observation) {
        $records[] = ['id' => $prefix.sprintf('%02d', $index + 1)] + $observation;
    }
    $document = [
        'environment' => [
            'php' => PHP_VERSION,
            'apisutra' => InstalledVersions::getPrettyVersion('brahmic/apisutra'),
            'reference' => InstalledVersions::getReference('brahmic/apisutra'),
        ],
        'observations' => $records,
    ];
    $json = json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo $json, PHP_EOL;

    if (in_array('--verify', $argv, true)) {
        $expected = json_decode(file_get_contents(__DIR__.'/'.$snapshot), true, flags: JSON_THROW_ON_ERROR);
        $actual = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if ($actual['observations'] !== $expected['observations']
            || $actual['environment']['reference'] !== $expected['environment']['reference']) {
            fwrite(STDERR, "The dependency or observations changed; review the differences.\n");
            exit(1);
        }
    }
}
