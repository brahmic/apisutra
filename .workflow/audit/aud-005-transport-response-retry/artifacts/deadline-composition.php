<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Brahmic\ApiSutra\Tests\Support\VirtualClock;
use Brahmic\ApiSutra\Timing\ExecutionBudget;
use Brahmic\ApiSutra\VO\Http\TransportOptions;

require dirname(__DIR__, 4) . '/vendor/autoload.php';

// Проверяем существующие примитивы; runtime API внешнего дедлайна пока отсутствует.
$clock = new VirtualClock();
$external = new ExecutionBudget($clock, 1000);
$start = $clock->monotonicMs();
$timeouts = [];
$stage = null;
for ($call = 0; $call < 3; $call++) {
    $budget = new ExecutionBudget($clock, 1000, $external);
    $effective = (new TransportOptions(30000, 10000, $budget))->effective();
    $timeouts[] = $effective->timeoutMs;
    // Моделируем только работу транспорта, который соблюдает переданный timeout.
    $clock->advance(min(400, $effective->timeoutMs));
    try {
        $budget->check('http_response');
    } catch (ExecutionDeadlineException $exception) {
        $stage = $exception->stage;
    }
}
if ($timeouts !== [1000, 600, 200] || $clock->monotonicMs() - $start !== 1000 || $stage !== 'http_response') {
    throw new RuntimeException('Нарушена композиция бюджетов');
}
echo "existing_budget_composition: timeouts=[1000,600,200], elapsed=1000, stage=http_response\n";

$clock = new VirtualClock();
$budget = new ExecutionBudget($clock, 1000);
$clock->advance(600);
try {
    $budget->wait(400, $clock, 'retry_wait');
    throw new RuntimeException('Ожидался отказ до sleep');
} catch (ExecutionDeadlineException $exception) {
    if ($clock->waits !== [] || $budget->remainingMs() !== 400) {
        throw new RuntimeException('Ожидание не должно расходовать остаток');
    }
    echo "existing_budget_wait: waits=[], remaining=400, stage={$exception->stage}\n";
}
