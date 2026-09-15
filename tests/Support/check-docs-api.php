<?php

declare(strict_types=1);

$checkout = $argv[1] ?? dirname(__DIR__, 2);
$manifest = $argv[2] ?? __DIR__ . '/docs-api.json';
require $checkout . '/vendor/autoload.php';
$entries = json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
$errors = [];
$methods = 0;
$parameters = 0;
foreach ($entries['declarations'] as $entry) {
    try {
        $class = new ReflectionClass($entry['class']);
        foreach ($entry['methods'] ?? [] as $name => $expected) {
            $method = $class->getMethod($name);
            ++$methods;
            if (!$method->isPublic()) {
                throw new RuntimeException($name . ' не public');
            }
            if ((string) $method->getReturnType() !== $expected['return']) {
                throw new RuntimeException($name . ': изменён возвращаемый тип');
            }
            $actual = [];
            foreach ($method->getParameters() as $parameter) {
                ++$parameters;
                // Reflection не вычисляет значения default и аргументы атрибутов.
                $actual[$parameter->getName()] = [
                    'type' => (string) $parameter->getType(),
                    'optional' => $parameter->isOptional(),
                    'variadic' => $parameter->isVariadic(),
                ];
            }
            if ($actual !== $expected['parameters']) {
                throw new RuntimeException($name . ': изменены имена, порядок или типы параметров');
            }
        }
    } catch (Throwable $error) {
        $errors[] = $entry['class'] . ': ' . $error->getMessage();
    }
    foreach ($entry['docs'] as $document) {
        if (!is_file($checkout . '/' . explode('#', $document)[0])) {
            $errors[] = $document . ': не найден владелец декларации';
        }
    }
}
echo json_encode([
    'classes' => count($entries['declarations']),
    'methods' => $methods,
    'parameters' => $parameters,
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
exit($errors === [] ? 0 : 1);
