<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use Brahmic\ApiSutra\Exceptions\Request\RateLimitException;
use Brahmic\ApiSutra\RateLimiting\RateLimiter;
use Brahmic\ApiSutra\Tests\Support\ArrayCache;

describe('RateLimiter', function () {
    it('выбрасывает исключение при превышении лимита', function () {
        $config = new RateLimitConfig(
            limit: 1,
            period: 60,
            behavior: RateLimitBehavior::Throw,
            store: new ArrayCache(),
        );

        $limiter = new RateLimiter();
        $key = 'rate:test';

        $limiter->acquire($config, $key);

        expect(fn () => $limiter->acquire($config, $key))
            ->toThrow(RateLimitException::class);
    });

    it('не делит лимиты между экземплярами', function () {
        $config = new RateLimitConfig(
            limit: 1,
            period: 60,
            behavior: RateLimitBehavior::Throw,
        );

        $key = 'rate:test';

        $limiterA = new RateLimiter();
        $limiterB = new RateLimiter();

        $limiterA->acquire($config, $key);
        $limiterB->acquire($config, $key);

        expect(fn () => $limiterA->acquire($config, $key))
            ->toThrow(RateLimitException::class);
    });
});
