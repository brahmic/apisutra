<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Auth;

use Brahmic\ApiSutra\VO\Http\PreparedRequest;

/** Отпечаток объявленных authenticator полей после подготовки запроса. */
final class CacheCredentialIdentity
{
    /** @param list<string> $headers */
    public static function forRequest(string $identity, ?PreparedRequest $request, array $headers, ?string $query = null): string
    {
        if ($request === null) {
            return $identity;
        }
        $selected = [];
        $names = array_map(strtolower(...), $headers);
        foreach ($request->headers as $name => $value) {
            $name = strtolower($name);
            if (in_array($name, $names, true)) {
                $selected[$name][] = $value;
            }
        }
        ksort($selected);
        $queryValues = [];
        if ($query !== null) {
            foreach (explode('&', parse_url($request->url, PHP_URL_QUERY) ?: '') as $part) {
                [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
                if (urldecode($name) === $query) {
                    $queryValues[] = urldecode($value);
                }
            }
        }

        return hash('sha256', serialize([$identity, $selected, $queryValues]));
    }
}
