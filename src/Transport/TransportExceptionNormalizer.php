<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Transport;

use Brahmic\ApiSutra\Exceptions\Transport\ConnectionException;
use Brahmic\ApiSutra\Exceptions\Transport\InvalidRequestException;
use Brahmic\ApiSutra\Exceptions\Transport\TimeoutException;
use Brahmic\ApiSutra\Exceptions\Transport\TransportException;
use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Throwable;

/** Нормализует только известные транспортные контракты, сохраняя исходную причину. */
final class TransportExceptionNormalizer
{
    public static function normalize(Throwable $exception): Throwable
    {
        if ($exception instanceof NetworkExceptionInterface) {
            // Guzzle опционален. Только подтверждённый код cURL, без анализа текста.
            if ($exception instanceof ConnectException && ($exception->getHandlerContext()['errno'] ?? null) === 28) {
                return new TimeoutException($exception->getMessage(), (int) $exception->getCode(), $exception);
            }
            return new ConnectionException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
        if ($exception instanceof RequestExceptionInterface) {
            return new InvalidRequestException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
        if ($exception instanceof ClientExceptionInterface) {
            return new TransportException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        return $exception;
    }
}
