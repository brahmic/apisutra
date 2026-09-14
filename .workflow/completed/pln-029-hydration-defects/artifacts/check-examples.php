<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';

$examples = [
    ['docs/guides/dto.md', '## Вложенные DTO'],
    ['docs/guides/attributes/data-transfer.md', '### Provider для найденного значения'],
];
foreach ($examples as [$file, $heading]) {
    $document = file_get_contents($root . '/' . $file);
    $section = strstr($document, $heading);
    if ($section === false || preg_match('/```php\R(.*?)\R```/s', $section, $match) !== 1) {
        throw new RuntimeException('Не найден пример: ' . $file);
    }
    // Выполняем дословный блок документации; вывод проверяется отдельно.
    ob_start();
    eval(preg_replace('/^<\?php\s*/', '', $match[1]));
    $output = ob_get_clean();
    if ($file === 'docs/guides/dto.md' && ($output !== 'Sample' || $user->address->city !== 'Sample')) {
        throw new RuntimeException('Неверный результат одиночного Nested');
    }
}
if ($dto->items[0]->city !== 'Sample' || $dto->items[1] !== ['kind' => 'future', 'enabled' => false]) {
    throw new RuntimeException('Неверный результат provider + Nested');
}
echo "Два примера выполнены дословно: одиночный Nested и Null/Present provider.\n";
