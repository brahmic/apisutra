<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Resolver;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * Реестр клиентов и привязок к namespace запросов.
 *
 * Хранит соответствие "namespace запроса → клиент" и гарантирует,
 * что запрос всегда попадёт к своему владельцу.
 */
final class ClientRegistry
{
    /**
     * @var array<string, ClientInterface>
     */
    private array $clients = [];

    /**
     * @var array<string, ClientInterface>
     */
    private array $resolved = [];

    /**
     * Зарегистрировать клиента по namespace запросов.
     *
     * По умолчанию namespace выводится из класса клиента (Root\\Requests).
     * При повторной регистрации одного namespace бросает исключение.
     */
    public function register(ClientInterface $client, ?string $requestNamespace = null, bool $force = false): void
    {
        $namespace = $this->normalizeNamespace(
            $requestNamespace ?? $this->inferRequestNamespace($client::class),
        );

        if (isset($this->clients[$namespace]) && !$force) {
            throw new ConfigurationException("Namespace '{$namespace}' уже зарегистрирован для клиента");
        }

        $this->clients[$namespace] = $client;
        $this->resolved = [];
    }

    /**
     * Разрешить клиента по классу запроса.
     *
     * Используется самый длинный совпадающий namespace, чтобы корректно
     * работать при вложенных структурах.
     */
    public function resolve(string $requestClass): ClientInterface
    {
        if (isset($this->resolved[$requestClass])) {
            return $this->resolved[$requestClass];
        }

        $client = $this->matchByNamespace($requestClass);
        if ($client === null) {
            throw new ConfigurationException("Клиент для запроса '{$requestClass}' не зарегистрирован");
        }

        return $this->resolved[$requestClass] = $client;
    }

    /**
     * Проверить, что запрос соответствует клиенту.
     *
     * Нужен для защиты от отправки запроса "чужого" клиента.
     */
    public function assertOwnership(ClientInterface $client, string $requestClass): void
    {
        $expected = $this->resolve($requestClass);
        if ($expected::class !== $client::class) {
            $expectedClass = $expected::class;
            $clientClass = $client::class;
            throw new ConfigurationException(
                "Запрос '{$requestClass}' принадлежит клиенту '{$expectedClass}', а не '{$clientClass}'",
            );
        }
    }

    private function matchByNamespace(string $requestClass): ?ClientInterface
    {
        $matched = null;
        $matchedLength = -1;

        foreach ($this->clients as $namespace => $client) {
            if ($requestClass === $namespace || str_starts_with($requestClass, $namespace . '\\')) {
                $length = strlen($namespace);
                if ($length > $matchedLength) {
                    $matched = $client;
                    $matchedLength = $length;
                }
            }
        }

        return $matched;
    }

    private function normalizeNamespace(string $namespace): string
    {
        return trim($namespace, '\\');
    }

    /**
     * По умолчанию ожидается namespace вида "Root\\Requests".
     */
    private function inferRequestNamespace(string $clientClass): string
    {
        $parts = explode('\\', trim($clientClass, '\\'));
        if (count($parts) < 2) {
            return $clientClass;
        }

        $root = implode('\\', array_slice($parts, 0, 2));
        return $root . '\\Requests';
    }
}
