<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Testing\LivePolling;

it('возвращает результат сразу при isReady на первом вызове', function (): void {
    $value = LivePolling::waitUntil(
        fetch: fn () => 'ready',
        isReady: fn ($v) => $v === 'ready',
        timeoutSeconds: 1,
    );

    expect($value)->toBe('ready');
});

it('повторяет вызовы до isReady', function (): void {
    $calls = 0;

    $value = LivePolling::waitUntil(
        fetch: function () use (&$calls) {
            $calls++;

            return $calls;
        },
        isReady: fn ($v) => $v >= 3,
        timeoutSeconds: 2,
        intervalMilliseconds: 10,
    );

    expect($value)->toBe(3)
        ->and($calls)->toBe(3);
});

it('бросает RuntimeException при таймауте', function (): void {
    LivePolling::waitUntil(
        fetch: fn () => 'never ready',
        isReady: fn () => false,
        timeoutSeconds: 1,
        intervalMilliseconds: 50,
        timeoutMessage: 'Custom timeout',
    );
})->throws(RuntimeException::class, 'Custom timeout');

it('включает last value в сообщение при таймауте', function (): void {
    try {
        LivePolling::waitUntil(
            fetch: fn () => (object) ['id' => 'x', 'status' => 'pending'],
            isReady: fn () => false,
            timeoutSeconds: 1,
            intervalMilliseconds: 50,
        );
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('last:')
            ->and($e->getMessage())->toContain('class=')
            ->and($e->getMessage())->toContain('id=x');
    }
});
