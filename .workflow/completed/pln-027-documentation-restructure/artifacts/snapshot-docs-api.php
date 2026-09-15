<?php

declare(strict_types=1);

// Снимок для первоначальной редакционной сверки; CI его не перегенерирует.
$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';
$selection = json_decode((string) file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$entries = [];
$errors = [];
foreach ($selection as $name => $documents) {
    try {
        $class = new ReflectionClass($name);
        $methods = [];
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $name || str_contains($method->getName(), 'Internal')) {
                continue;
            }
            if (str_contains((string) $method->getDocComment(), '@internal')) {
                continue;
            }
            $parameters = [];
            foreach ($method->getParameters() as $parameter) {
                $parameters[$parameter->getName()] = [
                    'type' => (string) $parameter->getType(),
                    'optional' => $parameter->isOptional(),
                    'variadic' => $parameter->isVariadic(),
                ];
            }
            $methods[$method->getName()] = ['return' => (string) $method->getReturnType(), 'parameters' => $parameters];
        }
        $entries[] = ['class' => $name, 'docs' => array_values($documents), 'methods' => $methods];
    } catch (Throwable $error) {
        $errors[] = $name . ': ' . $error->getMessage();
    }
}
file_put_contents($root . '/tests/Support/docs-api.json', json_encode([
    'description' => 'Явные декларации документации. Изменять после сверки контракта; не перегенерировать в CI. Значения default не вычисляются.',
    'declarations' => $entries,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);
echo json_encode(['classes' => count($entries), 'errors' => $errors], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
