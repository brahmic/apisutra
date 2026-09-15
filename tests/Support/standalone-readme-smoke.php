<?php

declare(strict_types=1);

$checkout = $argv[1] ?? dirname(__DIR__, 2);
// Выполняется опубликованный пример из проверяемого дистрибутива.
ob_start();
require $checkout . '/docs/example/sdk/run.php';
$output = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
$expected = [
    'id' => 7,
    'title' => 'Первая запись',
    'extra' => ['future_flag' => false],
    'failed' => true,
    'status' => 404,
];
if ($output !== $expected) {
    throw new RuntimeException('Опубликованный SDK вернул неверные данные или ошибку');
}
echo "Standalone published SDK — OK.\n";
