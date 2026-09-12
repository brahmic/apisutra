<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Transport\MockTransport;

$checkout = $argv[1] ?? '';
require $checkout . '/vendor/autoload.php';
$readme = file_get_contents($checkout . '/README.md');
if ($readme === false || preg_match('/```php\R(.*?)\R```/s', $readme, $match) !== 1) {
    throw new RuntimeException('Пример README не найден');
}
$transport = new MockTransport();
$transport->fake(['*' => MockResponse::success(['data' => ['id' => 1, 'name' => 'Alice']])]);
// Исполняется первый PHP-пример именно из проверяемого дистрибутива.
eval($match[1]);
if (!$user instanceof UserDto || $user->id !== 1 || $user->name !== 'Alice') {
    throw new RuntimeException('Пример README не вернул ожидаемый DTO');
}
echo "Standalone README example — OK.\n";
