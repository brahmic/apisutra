<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\RateLimiting\Backends;

use Brahmic\ApiSutra\Exceptions\Configuration\ConfigurationException;
use Brahmic\ApiSutra\Exceptions\RateLimiting\RateLimitBackendException;
use Brahmic\ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use Brahmic\ApiSutra\RateLimiting\RateLimitBackendInterface;
use Brahmic\ApiSutra\RateLimiting\RateLimitDecision;
use Brahmic\ApiSutra\RateLimiting\RateLimitQuota;
use Redis;
use RedisException;
use Throwable;

final class PhpRedisRateLimitBackend implements RateLimitBackendInterface
{
    private const int MAX_EXACT_INTEGER = 9_007_199_254_740_991;
    private readonly Redis $redis;
    private readonly string $prefix;
    private readonly string $script;
    private readonly string $sha;

    /** Выделенное готовое соединение принадлежит адаптеру; автоматические повторы отключаются. */
    public function __construct(object $redis, string $scope, private readonly int $ioTimeoutMs = 1000)
    {
        if (
            !extension_loaded('redis') || !$redis instanceof Redis
            || version_compare((string) phpversion('redis'), '6.2', '<')
        ) {
            throw new ConfigurationException(
                'Для PhpRedisRateLimitBackend требуется расширение phpredis >= 6.2 и объект Redis',
            );
        }
        if ($scope === '' || $ioTimeoutMs < 1 || $ioTimeoutMs > intdiv(PHP_INT_MAX, 1_000_000)) {
            throw new ConfigurationException(
                'Для Redis rate-limit требуются непустой scope и положительный представимый ioTimeoutMs',
            );
        }
        $this->redis = $redis;
        if (!$redis->isConnected()) {
            throw new ConfigurationException('Передайте открытое выделенное Redis-соединение');
        }
        $redis->setOption(Redis::OPT_MAX_RETRIES, 0);
        $this->checkOptions();
        // У phpredis 6.2 возврат READ_TIMEOUT=0 через setOption ломает следующее чтение.
        // Выделенному соединению задаём конечное значение до первого временного сокращения.
        if ($redis->getOption(Redis::OPT_READ_TIMEOUT) <= 0) {
            if (!$redis->setOption(Redis::OPT_READ_TIMEOUT, $ioTimeoutMs / 1000)) {
                throw new ConfigurationException('Не удалось задать конечный read timeout Redis');
            }
        }
        $script = file_get_contents(__DIR__ . '/../Resources/acquire.lua');
        if ($script === false) {
            throw new ConfigurationException('Не найден Lua resource rate-limit');
        }
        $this->script = $script;
        $this->sha = sha1($script);
        $this->prefix = 'apisutra:rate-limit:v3:' . hash('sha256', $scope) . ':';
    }

    public function tryAcquire(array $quotas, ?int $timeoutMs = null): RateLimitDecision
    {
        $quotas = RateLimitQuota::normalize($quotas);
        if ($quotas === []) {
            return new RateLimitDecision(true);
        }
        $keys = [];
        $arguments = [];
        foreach ($quotas as $quota) {
            if ($quota->limit > self::MAX_EXACT_INTEGER || $quota->periodMs > self::MAX_EXACT_INTEGER) {
                throw new ConfigurationException('Значение квоты выходит за точный целочисленный диапазон Redis Lua');
            }
            $keys[] = $this->prefix . hash('sha256', $quota->key);
            $arguments[] = (string) $quota->limit;
            $arguments[] = (string) $quota->periodMs;
        }
        $duration = min($this->ioTimeoutMs, $timeoutMs ?? $this->ioTimeoutMs);
        if ($duration <= 0) {
            throw new ExecutionDeadlineException('rate_limit_store');
        }
        $deadline = $this->nowMs() + $duration;
        $oldTimeout = null;
        try {
            if (!$this->redis->isConnected()) {
                throw new RateLimitBackendException();
            }
            $this->checkOptions();
            $oldTimeout = $this->redis->getOption(Redis::OPT_READ_TIMEOUT);
            $this->setTimeout($deadline);
            $this->redis->clearLastError();
            try {
                $reply = $this->redis->evalSha($this->sha, [...$keys, ...$arguments], count($keys));
                // phpredis может вернуть false вместо RedisException для ответа сервера.
                if ($reply === false) {
                    $error = $this->redis->getLastError();
                    if (!is_string($error) || !str_starts_with($error, 'NOSCRIPT ')) {
                        throw new RateLimitBackendException();
                    }
                    $this->setTimeout($deadline);
                    $reply = $this->redis->eval($this->script, [...$keys, ...$arguments], count($keys));
                }
            } catch (RedisException $exception) {
                if (!str_starts_with($exception->getMessage(), 'NOSCRIPT ')) {
                    throw $exception;
                }
                // Только NOSCRIPT доказывает отсутствие первой записи; сетевые ошибки не повторяются.
                $this->setTimeout($deadline);
                $reply = $this->redis->eval($this->script, [...$keys, ...$arguments], count($keys));
            }
            if ($this->nowMs() >= $deadline) {
                throw new RateLimitBackendException();
            }
            return $this->decision($reply, $quotas);
        } catch (ConfigurationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $exception instanceof RateLimitBackendException
                    ? $exception
                    : new RateLimitBackendException($exception);
        } finally {
            if ($oldTimeout !== null) {
                try {
                    if (!$this->redis->setOption(Redis::OPT_READ_TIMEOUT, $oldTimeout)) {
                        throw new RateLimitBackendException();
                    }
                } catch (Throwable $exception) {
                    throw $exception instanceof RateLimitBackendException
                    ? $exception
                    : new RateLimitBackendException($exception);
                }
            }
        }
    }

    private function checkOptions(): void
    {
        if (
            $this->redis->getMode() !== Redis::ATOMIC
            || $this->redis->getOption(Redis::OPT_SERIALIZER) !== Redis::SERIALIZER_NONE
            || $this->redis->getOption(Redis::OPT_COMPRESSION) !== Redis::COMPRESSION_NONE
            || $this->redis->getOption(Redis::OPT_MAX_RETRIES) !== 0
        ) {
            throw new ConfigurationException(
                'Redis rate-limit требует обычный режим, отсутствие serializer/compression и автоматических повторов',
            );
        }
    }

    private function nowMs(): int
    {
        return (int) (hrtime(true) / 1_000_000);
    }

    private function setTimeout(int $deadline): void
    {
        $remaining = $deadline - $this->nowMs();
        if ($remaining <= 0 || !$this->redis->setOption(Redis::OPT_READ_TIMEOUT, $remaining / 1000)) {
            throw new RateLimitBackendException();
        }
    }

    /** @param list<RateLimitQuota> $quotas */
    private function decision(mixed $reply, array $quotas): RateLimitDecision
    {
        if ($reply === [1]) {
            return new RateLimitDecision(true);
        }
        if ($reply === [-3]) {
            throw new ConfigurationException('Конец окна Redis-квоты не представим точно');
        }
        if ($reply === [-1]) {
            throw new ConfigurationException('Определение действующей Redis-квоты изменилось');
        }
        if (!is_array($reply) || !array_is_list($reply) || count($reply) < 3 || $reply[0] !== 0 || !is_int($reply[1])) {
            throw new RateLimitBackendException();
        }
        $blocked = [];
        foreach (array_slice($reply, 2) as $index) {
            if (!is_int($index) || $index < 1 || !isset($quotas[$index - 1])) {
                throw new RateLimitBackendException();
            }
            $blocked[] = $quotas[$index - 1]->key;
        }
        return new RateLimitDecision(false, $blocked, $reply[1]);
    }
}
