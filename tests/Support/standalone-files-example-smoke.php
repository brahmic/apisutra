<?php

declare(strict_types=1);

$checkout = $argv[1] ?? dirname(__DIR__, 2);
$example = $checkout . '/docs/example/files';

// Сверяем опубликованный пример: файловые байты, MIME, JSON и сохранение в путь/поток.
ob_start();
require $example . '/run.php';
$actual = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
$expected = json_decode(
    (string) file_get_contents($example . '/fixtures/expected.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if ($actual !== $expected) {
    throw new RuntimeException('Файловый пример: загрузка, скачивание или чтение архива изменились');
}

// Декларация запроса в рецепте должна совпадать с исполняемым примером.
$guide = (string) file_get_contents($checkout . '/docs/guides/recipes/files.md');
$source = trim(substr(
    (string) file_get_contents($example . '/src/Resources/Files/MultipartUploadRequest.php'),
    strlen('<?php'),
));
if (!str_contains($guide, "```php\n" . $source . "\n```")) {
    throw new RuntimeException('Файловый рецепт содержит устаревшую декларацию MultipartUploadRequest');
}

echo "Standalone files example — OK.\n";
