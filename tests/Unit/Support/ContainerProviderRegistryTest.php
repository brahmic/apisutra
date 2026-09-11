<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Brahmic\ApiSutra\Laravel\LaravelContainerProvider;
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Illuminate\Container\Container;

describe('ContainerProviderRegistry', function () {
    it('возвращает NullContainerProvider без явного провайдера', function () {
        ContainerProviderRegistry::set(new NullContainerProvider());

        $provider = ContainerProviderRegistry::resolve();

        expect($provider)->toBeInstanceOf(NullContainerProvider::class);
    });

    it('использует явный провайдер', function () {
        $provider = new class implements ContainerProviderInterface {
            #[\Override]
            public function bound(string $id): bool
            {
                return false;
            }

            #[\Override]
            public function make(string $id): ?object
            {
                return null;
            }

            #[\Override]
            public function basePath(): ?string
            {
                return null;
            }

            #[\Override]
            public function environment(): ?string
            {
                return null;
            }

            #[\Override]
            public function isDebug(): ?bool
            {
                return null;
            }

            #[\Override]
            public function validatorFactory(): ?object
            {
                return null;
            }
        };

        ContainerProviderRegistry::set($provider);

        expect(ContainerProviderRegistry::resolve())->toBe($provider);
    });

    it('resolve использует override поверх явного', function () {
        $explicit = new class implements ContainerProviderInterface {
            #[\Override]
            public function bound(string $id): bool
            {
                return false;
            }

            #[\Override]
            public function make(string $id): ?object
            {
                return null;
            }

            #[\Override]
            public function basePath(): ?string
            {
                return null;
            }

            #[\Override]
            public function environment(): ?string
            {
                return null;
            }

            #[\Override]
            public function isDebug(): ?bool
            {
                return null;
            }

            #[\Override]
            public function validatorFactory(): ?object
            {
                return null;
            }
        };
        $override = new class implements ContainerProviderInterface {
            #[\Override]
            public function bound(string $id): bool
            {
                return false;
            }

            #[\Override]
            public function make(string $id): ?object
            {
                return null;
            }

            #[\Override]
            public function basePath(): ?string
            {
                return '/override';
            }

            #[\Override]
            public function environment(): ?string
            {
                return null;
            }

            #[\Override]
            public function isDebug(): ?bool
            {
                return null;
            }

            #[\Override]
            public function validatorFactory(): ?object
            {
                return null;
            }
        };

        ContainerProviderRegistry::set($explicit);

        expect(ContainerProviderRegistry::resolve($override))->toBe($override);
    });

    it('auto-detect использует LaravelContainerProvider при наличии контейнера', function () {
        if (!class_exists(Container::class)) {
            $this->markTestSkipped('Illuminate Container недоступен');
        }

        $previous = Container::getInstance();
        $container = new Container();
        Container::setInstance($container);
        ContainerProviderRegistry::reset();

        try {
            $provider = ContainerProviderRegistry::resolve();
            expect($provider)->toBeInstanceOf(LaravelContainerProvider::class);
        } finally {
            Container::setInstance($previous);
            ContainerProviderRegistry::set(new NullContainerProvider());
        }
    });
});
