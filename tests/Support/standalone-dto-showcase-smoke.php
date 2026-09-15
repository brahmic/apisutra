<?php

declare(strict_types=1);

$checkout = $argv[1] ?? dirname(__DIR__, 2);
$example = $checkout . '/docs/example/dto-showcase';

// Проверяем опубликованные файлы и заранее заданные результаты, включая ошибки и wire JSON.
ob_start();
require $example . '/run.php';
$actual = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
$expected = json_decode(
    (string) file_get_contents($example . '/fixtures/expected.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if ($actual !== $expected) {
    throw new RuntimeException('Обзор DTO: преобразования, сериализация или диагностика изменились');
}

// Полные декларации в обзорной странице должны совпадать с исполняемыми исходниками.
$guide = (string) file_get_contents($checkout . '/docs/guides/dto/showcase.md');
preg_match_all('/```php\R(.*?)\R```/s', $guide, $matches);
$blocks = array_map(trim(...), $matches[1]);
foreach (['CatalogItemDto', 'CatalogRules'] as $class) {
    $source = trim(substr((string) file_get_contents($example . '/src/' . $class . '.php'), strlen('<?php')));
    if (!in_array($source, $blocks, true)) {
        throw new RuntimeException('Обзор DTO содержит устаревшую декларацию ' . $class);
    }
}

$input = trim((string) file_get_contents($example . '/fixtures/item.json'));
if (!str_contains($guide, "```json\n" . $input . "\n```")) {
    throw new RuntimeException('Входной JSON в обзоре расходится с фикстурой');
}

echo "Standalone DTO showcase — OK.\n";
