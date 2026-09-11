<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Brahmic\ApiSutra\Support\TransportResolver;
use Brahmic\ApiSutra\Tests\Support\FakeTransport;

beforeEach(function () {
    ContainerProviderRegistry::set(new NullContainerProvider());
});

describe('TransportResolver', function () {
    it('возвращает явно переданный transport', function () {
        $explicit = new FakeTransport();

        $resolved = TransportResolver::resolve($explicit);

        expect($resolved)->toBe($explicit);
    });

    it('резолвит transport из контейнера', function () {
        $transport = new FakeTransport();
        $provider = new class ($transport) implements ContainerProviderInterface {
            public function __construct(
                private readonly TransportInterface $transport,
            ) {}

            #[\Override]
            public function bound(string $id): bool
            {
                return $id === TransportInterface::class;
            }

            #[\Override]
            public function make(string $id): ?object
            {
                return $id === TransportInterface::class ? $this->transport : null;
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

        $resolved = TransportResolver::resolve(null, $provider);

        expect($resolved)->toBe($transport);
    });

    it('бросает исключение если TransportInterface не найден в контейнере', function () {
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

        expect(fn () => TransportResolver::resolve(null, $provider))
            ->toThrow(ConfigurationException::class, 'TransportInterface не найден в контейнере. Передайте transport явно.');
    });

    it('бросает исключение если контейнер вернул неверный тип transport', function () {
        $provider = new class implements ContainerProviderInterface {
            #[\Override]
            public function bound(string $id): bool
            {
                return $id === TransportInterface::class;
            }

            #[\Override]
            public function make(string $id): ?object
            {
                if ($id === TransportInterface::class) {
                    return new stdClass();
                }

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

        expect(fn () => TransportResolver::resolve(null, $provider))
            ->toThrow(ConfigurationException::class, 'Контейнер вернул некорректный transport. Ожидался экземпляр TransportInterface.');
    });
});
