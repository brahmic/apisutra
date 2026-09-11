<?php

declare(strict_types=1);

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Config\CredentialsEnrichmentConfig;
use Brahmic\ApiSutra\Config\CredentialsScopeConfig;
use Brahmic\ApiSutra\Config\DateTimeSerializationPolicy;
use Brahmic\ApiSutra\Config\DtoSerializationPolicy;
use Brahmic\ApiSutra\Contracts\Interfaces\Continuation\ContinuationModeApplicatorInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use Brahmic\ApiSutra\Tests\Stubs\Enrichment\TestRequestPartsEnricher;
use Brahmic\ApiSutra\Casts\BooleanCast;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;
use Brahmic\ApiSutra\Enums\Serialization\EnumOutput;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Result\ContinuationTokenExtractorInterface;
use Brahmic\ApiSutra\Result\ExecutionResult;
use Brahmic\ApiSutra\Result\ResultMetaExtractorInterface;
use Brahmic\ApiSutra\Serialization\VO\RequestPartsBag;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

describe('ClientConfig и AbstractClient', function () {
    it('отключает кеш метаданных в Testing и включает в Production', function () {
        $clientTesting = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            new MockTransport(),
        );
        $clientProd = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Production),
            new MockTransport(),
        );

        expect($clientTesting->getAttributeMetadataCache()->isEnabled())->toBeFalse()
            ->and($clientProd->getAttributeMetadataCache()->isEnabled())->toBeTrue();
    });

    it('ClientConfig::with переопределяет значения', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', timeout: 30, connectTimeout: 5);

        $updated = $config->with(baseUrl: 'https://api.next', timeout: 10);

        expect($updated->baseUrl)->toBe('https://api.next')
            ->and($updated->timeout)->toBe(10)
            ->and($updated->connectTimeout)->toBe(5);
    });

    it('ClientConfig::fromLaravel использует overrides', function () {
        $config = ClientConfig::fromLaravel([
            'baseUrl' => 'https://api.test',
            'debug' => true,
            'environment' => Environment::Testing,
        ]);

        expect($config->baseUrl)->toBe('https://api.test')
            ->and($config->debug)->toBeTrue()
            ->and($config->environment)->toBe(Environment::Testing);
    });

    it('ClientConfig::fromLaravel использует containerProvider для debug/environment', function () {
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
                return 'testing';
            }

            #[\Override]
            public function isDebug(): ?bool
            {
                return true;
            }

            #[\Override]
            public function validatorFactory(): ?object
            {
                return null;
            }
        };

        $config = ClientConfig::fromLaravel([
            'baseUrl' => 'https://api.test',
            'containerProvider' => $provider,
        ]);

        expect($config->debug)->toBeTrue()
            ->and($config->environment)->toBe(Environment::Testing);
    });

    it('валидирует baseUrl', function () {
        expect(fn () => new ClientConfig(baseUrl: ''))
            ->toThrow(ConfigurationException::class, 'ClientConfig.baseUrl не должен быть пустым');
    });

    it('валидирует числовые параметры', function (array $overrides, string $message) {
        $data = array_merge(['baseUrl' => 'https://api.test'], $overrides);

        expect(fn () => new ClientConfig(...$data))
            ->toThrow(ConfigurationException::class, $message);
    })->with([
        'timeout' => [['timeout' => -1], 'ClientConfig.timeout должен быть >= 0'],
        'connectTimeout' => [['connectTimeout' => -1], 'ClientConfig.connectTimeout должен быть >= 0'],
        'delay' => [['delay' => -1], 'ClientConfig.delay должен быть >= 0'],
        'authRetryAttempts' => [['authRetryAttempts' => -1], 'ClientConfig.authRetryAttempts должен быть >= 0'],
    ]);

    it('валидирует idempotencyHeader', function () {
        expect(fn () => new ClientConfig(baseUrl: 'https://api.test', idempotencyHeader: ' '))
            ->toThrow(ConfigurationException::class, 'ClientConfig.idempotencyHeader не должен быть пустым');
    });

    it('валидирует requestPartsEnumOutput', function () {
        expect(fn () => new ClientConfig(
            baseUrl: 'https://api.test',
            requestPartsEnumOutput: EnumOutput::Object,
        ))->toThrow(ConfigurationException::class, 'ClientConfig.requestPartsEnumOutput не может быть Object для query/header/path');
    });

    it('валидирует requestDateTime timezone', function () {
        expect(fn () => new ClientConfig(
            baseUrl: 'https://api.test',
            requestDateTime: new DateTimeSerializationPolicy(timezone: 'Bad/Timezone'),
        ))->toThrow(ConfigurationException::class, 'ClientConfig.requestDateTime.timezone содержит недопустимую timezone: Bad/Timezone');
    });

    it('валидирует casts', function (array $casts, string $message) {
        expect(fn () => new ClientConfig(baseUrl: 'https://api.test', casts: $casts))
            ->toThrow(ConfigurationException::class, $message);
    })->with([
        'class not found' => [['bad' => 'MissingCast'], 'ClientConfig.casts[bad] класс MissingCast не найден'],
        'class without interface' => [['bad' => stdClass::class], 'ClientConfig.casts[bad] ' . stdClass::class . ' должен реализовывать CastInterface'],
        'invalid object' => [['bad' => new stdClass()], 'ClientConfig.casts[bad] содержит недопустимое значение'],
    ]);

    it('принимает валидные casts', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            casts: [
                'bool' => BooleanCast::class,
                'bool_obj' => new BooleanCast(),
            ],
        );

        expect($config->casts)->toHaveCount(2);
    });

    it('валидирует requestEnrichers и credentialsConfig.scopes', function () {
        expect(fn () => new ClientConfig(
            baseUrl: 'https://api.test',
            requestEnrichers: [new stdClass()],
        ))->toThrow(
            ConfigurationException::class,
            'ClientConfig.requestEnrichers[0] должен реализовывать RequestPartsEnricherInterface',
        );

        expect(fn () => new ClientConfig(
            baseUrl: 'https://api.test',
            credentialsConfig: new CredentialsEnrichmentConfig(
                scopes: ['system' => new stdClass()],
            ),
        ))->toThrow(
            ConfigurationException::class,
            'ClientConfig.credentialsConfig.scopes[system] должен быть CredentialsScopeConfig',
        );

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            requestEnrichers: [new TestRequestPartsEnricher('trace', 'abc')],
            credentialsConfig: new CredentialsEnrichmentConfig(
                scopes: ['system' => new CredentialsScopeConfig()],
            ),
        );

        expect($config->requestEnrichers)->toHaveCount(1)
            ->and($config->credentialsConfig)->not->toBeNull();
    });

    it('принимает continuationTokenExtractor и сохраняет в with()', function () {
        $extractor = new class implements ContinuationTokenExtractorInterface {
            #[\Override]
            public function extract(ExecutionResult $result): ?string
            {
                return 'tok-1';
            }
        };

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            continuationTokenExtractor: $extractor,
        );
        $updated = $config->with(timeout: 5);

        expect($config->continuationTokenExtractor)->toBe($extractor)
            ->and($updated->continuationTokenExtractor)->toBe($extractor)
            ->and($updated->timeout)->toBe(5);
    });

    it('сохраняет wireBodySerializationPolicy в with()', function () {
        $wirePolicy = new DtoSerializationPolicy(enumOutput: EnumOutput::Object);
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            wireBodySerializationPolicy: $wirePolicy,
        );
        $updated = $config->with(timeout: 5);

        expect($config->wireBodySerializationPolicy)->toBe($wirePolicy)
            ->and($updated->wireBodySerializationPolicy)->toBe($wirePolicy)
            ->and($updated->timeout)->toBe(5);
    });

    it('принимает resultMetaExtractor и сохраняет в with()', function () {
        $extractor = new class implements ResultMetaExtractorInterface {
            #[\Override]
            public function extract(ExecutionResult $result): ?ResultMeta
            {
                return null;
            }
        };

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            resultMetaExtractor: $extractor,
        );
        $updated = $config->with(timeout: 7);

        expect($config->resultMetaExtractor)->toBe($extractor)
            ->and($updated->resultMetaExtractor)->toBe($extractor)
            ->and($updated->timeout)->toBe(7);
    });

    it('валидирует defaultPollRequest и continuationModeApplicator', function () {
        expect(fn () => new ClientConfig(
            baseUrl: 'https://api.test',
            defaultPollRequest: 'UnknownPollRequest',
        ))->toThrow(
            ConfigurationException::class,
            'ClientConfig.defaultPollRequest класс не найден: UnknownPollRequest',
        );

        expect(fn () => new ClientConfig(
            baseUrl: 'https://api.test',
            defaultPollRequest: stdClass::class,
        ))->toThrow(
            ConfigurationException::class,
            'ClientConfig.defaultPollRequest должен реализовывать RequestInterface',
        );

        $applicator = new class implements ContinuationModeApplicatorInterface {
            #[\Override]
            public function apply(
                RequestInterface $request,
                RequestPartsBag $parts,
                ContinuationMode $mode,
                ?PipelineContext $context = null,
            ): RequestPartsBag {
                return $parts;
            }
        };

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            defaultContinuationMode: ContinuationMode::Async,
            defaultPollRequest: ContinuationPollRequest::class,
            continuationModeApplicator: $applicator,
        );
        $updated = $config->with(defaultContinuationMode: ContinuationMode::Sync);

        expect($config->defaultPollRequest)->toBe(ContinuationPollRequest::class)
            ->and($config->defaultContinuationMode)->toBe(ContinuationMode::Async)
            ->and($config->continuationModeApplicator)->toBe($applicator)
            ->and($updated->defaultContinuationMode)->toBe(ContinuationMode::Sync)
            ->and($updated->continuationModeApplicator)->toBe($applicator);
    });
});
