<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Resolver;

use Brahmic\ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * Определяет namespace запросов, принадлежащих клиенту.
 *
 * Делает root‑scan по классу клиента, а при отсутствии совпадений
 * использует fallback‑конвенции.
 */
final readonly class RequestNamespaceDetector
{
    public function __construct(
        private RequestScanner $scanner,
    ) {}

    /**
     * Обнаружить namespace запросов клиента.
     *
     * @return array<int, string>
     */
    public function detect(ClientInterface $client): array
    {
        $root = $this->inferRootNamespace($client::class);
        $conventions = $this->conventionalNamespaces($root);

        $classes = $this->scanner->scanRoot($root);
        if ($classes === []) {
            $classes = $this->scanner->scanNamespaces($conventions);
        }

        if ($classes === []) {
            $clientClass = $client::class;
            throw new ConfigurationException("Не найдены запросы клиента '{$clientClass}'");
        }

        $namespaces = [];
        foreach ($classes as $class) {
            $pos = strrpos($class, '\\');
            if ($pos !== false) {
                $namespaces[] = substr($class, 0, $pos);
            }
        }

        return array_values(array_unique($namespaces));
    }

    /**
     * Вычислить root‑namespace по имени класса клиента.
     *
     * По умолчанию берутся первые два сегмента: Vendor\\Package.
     */
    private function inferRootNamespace(string $clientClass): string
    {
        $parts = explode('\\', trim($clientClass, '\\'));
        if (count($parts) < 2) {
            return $clientClass;
        }

        return implode('\\', array_slice($parts, 0, 2));
    }

    /**
     * Конвенциональные namespace запросов.
     *
     * @return array<int, string>
     */
    private function conventionalNamespaces(string $root): array
    {
        return [
            $root . '\\Requests',
            $root . '\\Resources',
        ];
    }
}
