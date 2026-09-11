<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Pipeline\Auth\AuthRefreshLock;
use Brahmic\ApiSutra\Tests\Support\LockingCache;

describe('AuthRefreshLock', function () {
    it('блокирует и освобождает lock в кеше', function () {
        $cache = new LockingCache();
        $lock = new AuthRefreshLock($cache);
        $key = 'auth_refresh_lock:test';

        $token = $lock->acquire($key, 5);
        expect($token)->not->toBeNull()
            ->and($cache->has($key))->toBeTrue();

        $second = $lock->acquire($key, 5);
        expect($second)->toBeNull();

        $lock->release($key, (string) $token);
        expect($cache->has($key))->toBeFalse()
            ->and($cache->lastDeleteKey)->toBe($key);
    });

    it('работает без кеша', function () {
        $lock = new AuthRefreshLock(null);
        $key = 'auth_refresh_lock:local';

        $token = $lock->acquire($key, 5);
        $second = $lock->acquire($key, 5);
        $lock->release($key, (string) $token);
        $third = $lock->acquire($key, 5);

        expect($token)->not->toBeNull()
            ->and($second)->toBeNull()
            ->and($third)->not->toBeNull();
    });

    it('не освобождает lock при неверном токене', function () {
        $cache = new LockingCache();
        $lock = new AuthRefreshLock($cache);
        $key = 'auth_refresh_lock:wrong-token';

        $token = $lock->acquire($key, 5);
        $lock->release($key, 'wrong');

        expect($cache->has($key))->toBeTrue()
            ->and($cache->lastDeleteKey)->toBeNull();

        $lock->release($key, (string) $token);
        expect($cache->has($key))->toBeFalse();
    });

    it('позволяет повторно захватить lock после истечения TTL', function () {
        $cache = new LockingCache();
        $lock = new AuthRefreshLock($cache);
        $key = 'auth_refresh_lock:ttl';

        $first = $lock->acquire($key, 1);
        expect($first)->not->toBeNull();

        usleep(2100000);

        $second = $lock->acquire($key, 1);
        expect($second)->not->toBeNull();
    });
});
