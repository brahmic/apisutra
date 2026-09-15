<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Auth\BearerAuthenticator;
use Brahmic\ApiSutra\Config\CacheConfig;
use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\RetryConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Cache\CacheIdentityProviderInterface;
use Brahmic\ApiSutra\Enums\Cache\CacheMode;
use Brahmic\ApiSutra\Serialization\Rules\HydrationRules;
use Brahmic\ApiSutra\Tests\Support\ClockCache;
use Brahmic\ApiSutra\Tests\Support\TestAuthLockProvider;
use Brahmic\ApiSutra\Tests\Support\TestClientFactory;
use Brahmic\ApiSutra\Tests\Support\VirtualClock;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Psr\SimpleCache\CacheInterface;

it('сохраняет каждый независимый аргумент при пустой и несвязанной копии', function (bool $hasStore, bool $hasConfig): void {
    $store = $hasStore ? new ClockCache(new VirtualClock()) : null;
    $params = $hasConfig ? new CacheConfig(ttl: 60, prefix: 'copy', mode: CacheMode::Disabled) : null;
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheStore: $store, cacheConfig: $params);
    foreach ([$config, $config->with(), $config->with(timeout: 7), $config->with()->with(timeout: 7)] as $copy) {
        expect($copy->cacheStore)->toBe($store)->and($copy->cacheConfig)->toBe($params);
    }
    expect($config->with())->not->toBe($config)->and($config->timeout)->toBe(30)
        ->and($config->with(timeout: 7)->timeout)->toBe(7);
})->with([true, false])->with([true, false]);

it('заменяет настройки целиком и сохраняет ссылки без IO, очищения и клонирования', function (): void {
    $clock = new VirtualClock();
    $store = new ClockCache($clock);
    $store->entries = ['existing' => ['value' => 'untouched', 'expires' => null]];
    $other = new ClockCache($clock);
    $identity = new class implements CacheIdentityProviderInterface {
        public function getCacheIdentity(?PreparedRequest $request = null): ?string
        {
            throw new LogicException('Конфигурирование не должно читать identity');
        }
    };
    $locks = new TestAuthLockProvider($clock);
    $params = new CacheConfig(ttl: 60, prefix: 'old', mode: CacheMode::Disabled, identity: $identity, locks: $locks);
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheStore: $store, cacheConfig: $params);
    $replacement = new CacheConfig(ttl: 10);
    $changed = $config->with(cacheConfig: $replacement);
    expect($changed->cacheStore)->toBe($store)->and($changed->cacheConfig)->toBe($replacement)
        ->and($replacement->prefix)->toBe('')->and($replacement->mode)->toBe(CacheMode::Enabled)
        ->and($replacement->identity)->toBeNull()->and($replacement->locks)->toBeNull();
    $moved = $config->with(cacheStore: $other);
    expect($moved->cacheStore)->toBe($other)->and($moved->cacheConfig)->toBe($params)
        ->and($params->identity)->toBe($identity)->and($params->locks)->toBe($locks);
    foreach (
        [
        $config->with(cacheStore: $other, cacheConfig: $replacement),
        $config->with(cacheStore: $other)->with(cacheConfig: $replacement),
        $config->with(cacheConfig: $replacement)->with(cacheStore: $other),
        ] as $copy
    ) {
        expect($copy->cacheStore)->toBe($other)->and($copy->cacheConfig)->toBe($replacement);
    }
    foreach (
        [
        $config->with(cacheStore: null),
        $config->with(cacheStore: null)->with()->with(timeout: 7),
        ] as $copy
    ) {
        expect($copy->cacheStore)->toBeNull()->and($copy->cacheConfig)->toBe($params);
    }
    expect($config->with(cacheConfig: null)->cacheStore)->toBe($store)
        ->and($config->with(cacheConfig: null)->cacheConfig)->toBeNull()
        ->and($config->with(cacheStore: null, cacheConfig: null)->cacheConfig)->toBeNull()
        ->and($config->with(cacheStore: null, cacheConfig: $replacement)->cacheConfig)->toBe($replacement)
        ->and($config->with(cacheStore: $other, cacheConfig: null)->cacheStore)->toBe($other)
        ->and($config->cacheStore)->toBe($store)->and($config->cacheConfig)->toBe($params)
        ->and($store->entries)->toBe(['existing' => ['value' => 'untouched', 'expires' => null]])
        ->and($store->events)->toBe([])->and($other->events)->toBe([])->and($locks->keys)->toBe([]);
});

it('отклоняет старые именованные аргументы и неверные типы без доступа к backend', function (): void {
    $store = new ClockCache(new VirtualClock());
    $config = new ClientConfig(baseUrl: 'https://copy.test', cacheStore: $store);
    foreach (
        [
        fn () => new ClientConfig(baseUrl: 'https://copy.test', cache: $store),
        fn () => $config->with(cache: null),
        fn () => $config->with(cache: $store, cacheStore: $store),
        fn () => new ClientConfig(...['baseUrl' => 'https://copy.test', 'cache' => $store]),
        fn () => ClientConfig::fromLaravel(['baseUrl' => 'https://copy.test', 'cache' => $store]),
        fn () => new CacheConfig(store: $store),
        ] as $call
    ) {
        expect($call)->toThrow(Error::class);
    }
    expect(fn () => new ClientConfig(baseUrl: 'https://copy.test', cacheStore: new CacheConfig()))->toThrow(TypeError::class);
    expect(fn () => $config->with(cacheConfig: $store))->toThrow(TypeError::class);
    expect($store->events)->toBe([]);
});

it('сохраняет позиции параметров, readonly типизацию, массивы, Laravel и независимые поля', function (): void {
    $store = new ClockCache(new VirtualClock());
    $params = new CacheConfig(10, 'prefix', CacheMode::ReadOnly);
    $config = new ClientConfig('https://copy.test', null, [], null, true, 1, null, 'info', $store, $params, 7);
    expect($config->cacheStore)->toBe($store)->and($config->cacheConfig)->toBe($params)->and($config->timeout)->toBe(7);
    $class = new ReflectionClass(ClientConfig::class);
    expect($class->hasProperty('cache'))->toBeFalse()->and($class->getProperty('cacheStore')->isReadOnly())->toBeTrue()
        ->and((string) $class->getProperty('cacheStore')->getType())->toBe('?' . CacheInterface::class)
        ->and((new ReflectionClass(CacheConfig::class))->hasProperty('store'))->toBeFalse();
    foreach (
        [new ClientConfig(...['baseUrl' => 'https://copy.test', 'cacheStore' => $store, 'cacheConfig' => $params]),
        ClientConfig::fromLaravel(['baseUrl' => 'https://copy.test', 'cacheStore' => $store, 'cacheConfig' => $params]),
        ] as $copy
    ) {
        expect($copy->cacheStore)->toBe($store)->and($copy->cacheConfig)->toBe($params);
    }
    $retry = new RetryConfig();
    $config = $config->with(auth: new BearerAuthenticator('synthetic'), hydrationRules: HydrationRules::create());
    $copy = $config->with(auth: null, hydrationRules: null, retry: $retry, authScopes: []);
    expect($copy->auth)->toBeNull()->and($copy->hydrationRules)->toBeNull()->and($copy->retry)->toBe($retry)
        ->and($copy->cacheStore)->toBe($store)->and($copy->authScopes)->toBe([]);
});

it('TestClientFactory сохраняет явные null без запасного store', function (): void {
    $client = TestClientFactory::make(overrides: ['cacheStore' => null, 'cacheConfig' => null]);
    expect($client->getConfig()->cacheStore)->toBeNull()->and($client->getConfig()->cacheConfig)->toBeNull();
});
