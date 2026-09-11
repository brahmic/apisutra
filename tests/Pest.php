<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Тестовый стенд
|--------------------------------------------------------------------------
|
| Тесты ядра и Laravel-интеграции без запуска приложения.
| Используем базовый PHPUnit TestCase.
|
*/

use Brahmic\ApiSutra\Tests\Support\TestTrait;
use Brahmic\ApiSutra\Tests\Support\DiscoveryTestHelper;

// Регистрируем test-only namespace один раз на весь ран Pest.
DiscoveryTestHelper::ensurePsr4Registered('Acme\\Blank\\', __DIR__ . '/Fixtures/Acme/Blank');
DiscoveryTestHelper::ensurePsr4Registered('Acme\\Discovery\\', __DIR__ . '/Fixtures/Acme/Discovery');
DiscoveryTestHelper::ensurePsr4Registered('Acme\\Fallback\\', __DIR__ . '/Fixtures/Acme/Fallback');

uses(TestTrait::class)
    ->beforeEach(function (): void {
        $this->resetApiSutraState();
    })
    ->afterEach(function (): void {
        $this->resetApiSutraState();
    })
    ->in(__DIR__);
pest()->in(__DIR__);
