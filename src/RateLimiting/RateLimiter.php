<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\RateLimiting;

use Brahmic\ApiSutra\Config\RateLimitConfig;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use Brahmic\ApiSutra\Enums\Http\HttpMethod;
use Brahmic\ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use Brahmic\ApiSutra\Exceptions\Request\RateLimitException;
use Brahmic\ApiSutra\Timing\ExecutionBudget;
use Brahmic\ApiSutra\Timing\SystemClock;
use Brahmic\ApiSutra\Timing\SystemSleeper;
use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Psr\SimpleCache\CacheInterface;

/**
 * Лимитер запросов с in-memory и/или PSR-16 хранилищем.
 *
 * Нюансы:
 * - Без внешнего store лимиты хранятся в памяти экземпляра (per-client).
 * - Ключ лимита формируется снаружи и должен включать нужный scope (например, baseUrl/endpoint).
 * - Не следует хешировать параметры запроса по умолчанию, чтобы не размазывать лимиты.
 * - При использовании store лимиты могут быть общими между процессами.
 */
final class RateLimiter
{
    /**
     * @var array<string, array{count: int, reset: int}> Локальное состояние лимитов
     */
    private array $memory = [];

    public function __construct(
        private readonly ClockInterface $clock = new SystemClock(),
        private readonly SleeperInterface $sleeper = new SystemSleeper(),
    ) {}

    /**
     * Получить слот лимита по ключу или применить ожидание/исключение.
     */
    public function acquire(RateLimitConfig $config, string $key, ?ExecutionBudget $budget = null): void
    {
        $budget ??= new ExecutionBudget($this->clock);
        $budget->check('rate_limit');
        $store = $config->store;
        $now = $budget->clock->unixTime();

        $data = $this->loadState($store, $key, $config->period, $now);
        $budget->check('rate_limit_store');
        if ($this->consumeIfAvailable($store, $key, $data, $config->limit, $config->period)) {
            return;
        }

        $this->handleLimitExceeded($config, $data, $now, $budget);
        $data = $this->startNewWindow($config->period, $budget->clock->unixTime());
        $data['count'] = 1;
        $this->store($store, $key, $data, $config->period);
    }

    /**
     * Загрузить состояние лимита из store или локальной памяти.
     *
     * @return array{count: int, reset: int}
     */
    private function loadState(?CacheInterface $store, string $key, int $period, int $now): array
    {
        // Загружаем состояние из store или из локальной памяти
        $data = $store?->get($key) ?? $this->memory[$key] ?? null;
        if (!is_array($data) || ($data['reset'] ?? 0) <= $now) {
            return $this->startNewWindow($period, $now);
        }

        return $data;
    }

    /**
     * Попробовать списать лимит. Возвращает true при успехе.
     *
     * @param array{count: int, reset: int} $data
     */
    private function consumeIfAvailable(?CacheInterface $store, string $key, array $data, int $limit, int $period): bool
    {
        if ($data['count'] >= $limit) {
            return false;
        }

        // Лимит ещё не исчерпан
        $data['count']++;
        $this->store($store, $key, $data, $period);

        return true;
    }

    /**
     * Обработать превышение лимита: исключение или ожидание.
     *
     * @param array{count: int, reset: int} $data
     */
    private function handleLimitExceeded(RateLimitConfig $config, array $data, int $now, ExecutionBudget $budget): void
    {
        if ($config->behavior === RateLimitBehavior::Throw) {
            // Явная ошибка при превышении лимита
            throw $this->buildException($data, $now);
        }

        $sleepFor = max(0, $data['reset'] - $now);
        if ($sleepFor > 0) {
            // Ожидание следующего окна лимита
            $budget->wait($sleepFor * 1000, $this->sleeper, 'rate_limit_wait');
        }
    }

    /**
     * Сформировать исключение о превышении лимита.
     *
     * @param array{count: int, reset: int} $data
     */
    private function buildException(array $data, int $now): RateLimitException
    {
        return new RateLimitException(
            'Превышен лимит запросов',
            new ProviderResponse(429, [], '', new PreparedRequest(
                method: HttpMethod::GET,
                url: '',
            ), 0),
            $data['reset'] - $now,
        );
    }

    /**
     * Создать новое окно лимита.
     *
     * @return array{count: int, reset: int}
     */
    private function startNewWindow(int $period, int $now): array
    {
        // Новое окно лимита
        return ['count' => 0, 'reset' => $now + $period];
    }

    /**
     * Сохранить состояние лимита в store или локально.
     *
     * @param array{count: int, reset: int} $data
     */
    private function store(?CacheInterface $store, string $key, array $data, int $ttl): void
    {
        if ($store !== null) {
            $store->set($key, $data, $ttl);
            return;
        }

        $this->memory[$key] = $data;
    }
}
