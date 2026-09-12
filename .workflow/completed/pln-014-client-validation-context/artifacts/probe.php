<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Workflow\ValidationProbe;

use Brahmic\ApiSutra\Config\ClientConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use Brahmic\ApiSutra\Enums\Configuration\Environment;
use Brahmic\ApiSutra\Support\ContainerProviderRegistry;
use Brahmic\ApiSutra\Support\NullContainerProvider;
use Brahmic\ApiSutra\Testing\MockResponse;
use Brahmic\ApiSutra\Tests\Stubs\TestClient;
use Brahmic\ApiSutra\Transport\MockTransport;
use Brahmic\ApiSutra\VO\Validation\Validator;
use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use ReflectionProperty;
use stdClass;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
require __DIR__ . '/ProbeProvider.php';
require __DIR__ . '/ProbeRequest.php';
require __DIR__ . '/ProbeDto.php';

function factory(bool $accept, string $label): Factory
{
    $factory = new Factory(new Translator(new ArrayLoader(), 'en'));
    $factory->extend('fixture_rule', static fn (): bool => $accept, $label);
    return $factory;
}

function resetState(): void
{
    // Только изолированный probe: у текущего Validator нет публичного сброса factory.
    (new ReflectionProperty(Validator::class, 'factory'))->setValue(null, null);
    ContainerProviderRegistry::set(new NullContainerProvider());
}

function observe(string $case, ?ContainerProviderInterface $provider = null, bool $async = false): void
{
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['ok' => true])]);
    $request = (new ProbeRequest())->setClient(new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', environment: Environment::Testing, containerProvider: $provider,
    ), $transport));
    $manualPassed = $request->isValid();
    $result = ($async ? $request->sendAsync() : $request->send())->raw();
    echo json_encode([
        'case' => $case, 'success' => $result->isSuccess(), 'manual_passed' => $manualPassed,
        'error' => $result->errors->first()?->code->value,
        'validation_message' => $result->validationErrors[0]->message ?? null,
        'http_calls' => count($transport->getRecorded()),
        'client_factory_calls' => $provider instanceof ProbeProvider ? $provider->factoryCalls : null,
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
}

resetState();
observe('client_only_reject', new ProbeProvider(factory(false, 'client-reject')));
resetState();
ContainerProviderRegistry::set(new ProbeProvider(factory(false, 'global-reject')));
observe('global_control');
resetState();
ContainerProviderRegistry::set(new ProbeProvider(factory(true, 'global-accept')));
observe('global_accept_client_reject', new ProbeProvider(factory(false, 'client-reject')));
resetState();
ContainerProviderRegistry::set(new ProbeProvider(factory(false, 'global-reject')));
observe('global_reject_client_accept', new ProbeProvider(factory(true, 'client-accept')));
resetState();
Validator::useFactory(factory(false, 'static-reject'));
observe('static_reject_client_accept', new ProbeProvider(factory(true, 'client-accept')));
resetState();
observe('missing_factory');
observe('async_client_only_reject', new ProbeProvider(factory(false, 'client-reject')), true);
resetState();
ContainerProviderRegistry::set(new ProbeProvider(new stdClass()));
observe('invalid_factory_object');
resetState();
$providerA = new ProbeProvider(factory(true, 'A-accept'));
$providerB = new ProbeProvider(factory(false, 'B-reject'));
observe('sequence_A1', $providerA);
observe('sequence_B', $providerB);
observe('sequence_A2', $providerA);
resetState();
Validator::useFactory(factory(false, 'static-survives-registry-reset'));
ContainerProviderRegistry::reset();
ContainerProviderRegistry::set(new NullContainerProvider());
observe('registry_reset_keeps_static_factory');
resetState();
echo json_encode(['case' => 'dto_without_factory', 'valid' => (new ProbeDto())->isValid()], JSON_THROW_ON_ERROR) . PHP_EOL;
$previous = Container::getInstance();
try {
    $container = new Container();
    $container->instance('validator', factory(false, 'autodetected-reject'));
    Container::setInstance($container);
    ContainerProviderRegistry::reset();
    observe('autodetect_control');
} finally {
    Container::setInstance($previous);
    resetState();
}
