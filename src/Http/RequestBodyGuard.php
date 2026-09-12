<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Http;

use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Throwable;

/** Проверяет явно заданный framing без чтения или перемотки тела. */
final class RequestBodyGuard
{
    public static function check(PreparedRequest $request): void
    {
        if ($request->body !== null && $request->stream !== null) {
            throw new ConfigurationException('HTTP-тело не может одновременно содержать строку и поток');
        }
        $lengths = [];
        $hasTransferEncoding = false;
        foreach ($request->headers as $name => $value) {
            if (strcasecmp($name, 'Content-Length') === 0) {
                $lengths[] = trim($value);
            } elseif (strcasecmp($name, 'Transfer-Encoding') === 0) {
                $hasTransferEncoding = true;
            }
        }
        if ($lengths === []) {
            return;
        }
        if ($hasTransferEncoding || count($lengths) !== 1 || !preg_match('/^[0-9]+$/D', $lengths[0])) {
            throw new ConfigurationException('Некорректные Content-Length/Transfer-Encoding запроса');
        }

        $size = strlen($request->body ?? '');
        if ($request->stream !== null) {
            try {
                $size = $request->stream->getSize();
                if ($size !== null) {
                    $size = max(0, $size - $request->stream->tell());
                }
            } catch (Throwable) {
                // Неизвестную длину нельзя выяснять потреблением пользовательского потока.
                $size = null;
            }
        }
        if ($size !== null && (ltrim($lengths[0], '0') ?: '0') !== (string) $size) {
            throw new ConfigurationException('Content-Length не совпадает с размером HTTP-тела');
        }
    }
}
