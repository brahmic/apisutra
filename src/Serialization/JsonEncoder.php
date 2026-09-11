<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization;

use Brahmic\ApiSutra\Exceptions\Serialization\SerializationException;
use JsonException;

final class JsonEncoder
{
    public static function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            // Сообщение не содержит исходных значений payload.
            throw new SerializationException('Не удалось сериализовать JSON: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
