<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Config;

use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Http\Origin;

/** Разрешает явный выбор credentials для точных origin; сама авторизацию не включает. */
final readonly class OriginPolicy
{
    /** @var list<string> */
    private array $allowedOrigins;

    /** @param list<string> $allowedOrigins */
    public function __construct(array $allowedOrigins = [])
    {
        $normalized = [];
        foreach ($allowedOrigins as $origin) {
            if (!is_string($origin)) {
                throw new ConfigurationException('Origin policy ожидает список origin');
            }
            $parts = parse_url($origin);
            if (
                $parts === false || isset($parts['pass']) || isset($parts['user'])
                || isset($parts['query']) || isset($parts['fragment']) || ($parts['path'] ?? '') !== ''
            ) {
                throw new ConfigurationException('В allowlist требуется только схема, host и порт');
            }
            $normalized[] = Origin::fromUrl($origin);
        }
        $this->allowedOrigins = array_values(array_unique($normalized));
    }

    public function allows(string $origin): bool
    {
        return in_array($origin, $this->allowedOrigins, true);
    }
}
