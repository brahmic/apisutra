<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

// В установленном пакете Composer находится выше vendor/brahmic/apisutra.
$autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    $autoload = dirname(__DIR__, 5) . '/autoload.php';
}
/** @var ClassLoader $loader */
$loader = require $autoload;
$loader->addPsr4('Example\\Records\\', __DIR__ . '/src/');
$loader->addPsr4('Example\\HydrationRules\\', __DIR__ . '/../hydration-rules/src/');
$loader->addPsr4('Example\\Continuation\\', __DIR__ . '/../continuation/src/');
