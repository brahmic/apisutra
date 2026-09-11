<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Support;

use Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Brahmic\ApiSutra\Laravel\LaravelContainerProvider;
use Illuminate\Container\Container;

/**
 * Глобальный реестр провайдера контейнера.
 */
final class ContainerProviderRegistry
{
    private static ?ContainerProviderInterface $explicit = null;
    private static ?ContainerProviderInterface $resolved = null;

    public static function set(ContainerProviderInterface $provider): void
    {
        self::$explicit = $provider;
        self::$resolved = $provider;
    }

    public static function reset(): void
    {
        self::$explicit = null;
        self::$resolved = null;
    }

    public static function resolve(?ContainerProviderInterface $override = null): ContainerProviderInterface
    {
        if ($override !== null) {
            return $override;
        }

        if (self::$resolved instanceof ContainerProviderInterface) {
            return self::$resolved;
        }

        $provider = self::$explicit ?? self::autoDetect();
        self::$resolved = $provider;

        return $provider;
    }

    private static function autoDetect(): ContainerProviderInterface
    {
        $container = self::resolveContainerFromApp();
        if ($container !== null) {
            return new LaravelContainerProvider($container);
        }

        $container = self::resolveContainerFromIlluminate();
        if ($container !== null) {
            return new LaravelContainerProvider($container);
        }

        return new NullContainerProvider();
    }

    private static function resolveContainerFromApp(): ?object
    {
        if (!function_exists('app')) {
            return null;
        }

        $app = app();
        if (!is_object($app)) {
            return null;
        }

        return self::isCompatibleContainer($app) ? $app : null;
    }

    private static function resolveContainerFromIlluminate(): ?object
    {
        if (!class_exists(Container::class)) {
            return null;
        }

        $container = Container::getInstance();
        if (!is_object($container)) {
            return null;
        }

        return self::isCompatibleContainer($container) ? $container : null;
    }

    private static function isCompatibleContainer(object $container): bool
    {
        return method_exists($container, 'bound')
            && method_exists($container, 'make');
    }
}
