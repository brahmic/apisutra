<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Exceptions\Serialization;

use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Throwable;

class HydrationException extends SdkException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $reason = null,
        public readonly ?string $path = null,
        public readonly ?string $expected = null,
        public readonly ?string $actual = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function invalidValue(string $reason, string $expected, string $actual, string $path = '', ?Throwable $previous = null): self
    {
        return new self(
            'Некорректные данные в ' . ($path === '' ? '$' : $path) . ': ожидается ' . $expected . ', получено ' . $actual,
            previous: $previous,
            reason: $reason,
            path: $path,
            expected: $expected,
            actual: $actual,
        );
    }

    /** Дополняет только структурированную диагностику; значения ответа не включаются. */
    public function prependPath(string $prefix): self
    {
        if ($this->reason === null) {
            return $this;
        }

        $path = $this->path ?? '';
        $path = $prefix . ($path === '' || str_starts_with($path, '[') ? '' : '.') . $path;
        return new self(
            'Некорректные данные в ' . $path . ': ожидается ' . ($this->expected ?? 'unknown') . ', получено ' . ($this->actual ?? 'unknown'),
            $this->getCode(),
            $this,
            reason: $this->reason,
            path: $path,
            expected: $this->expected,
            actual: $this->actual,
        );
    }

    /** @return array<string, string> */
    public function context(): array
    {
        return array_filter([
            'reason' => $this->reason,
            'path' => $this->path,
            'expected' => $this->expected,
            'actual' => $this->actual,
        ], static fn (?string $value): bool => $value !== null);
    }
}
