<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Http;

use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;

final class Origin
{
    public static function fromUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (
            !in_array($scheme, ['http', 'https'], true) || $host === ''
            || preg_match('/[^a-z0-9.\-:\[\]]/', $host)
        ) {
            throw new ConfigurationException('URL должен содержать HTTP/HTTPS origin с ASCII host');
        }
        if (str_starts_with($host, '[') && filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            throw new ConfigurationException('Некорректный IPv6 origin');
        }
        if (str_starts_with($host, '[')) {
            $host = '[' . inet_ntop(inet_pton(trim($host, '[]'))) . ']';
        }
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) {
            throw new ConfigurationException('Некорректный порт origin');
        }
        return $scheme . '://' . $host . ':' . $port;
    }
}
