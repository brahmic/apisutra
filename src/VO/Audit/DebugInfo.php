<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\VO\Audit;

use Brahmic\ApiSutra\VO\Http\PreparedRequest;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;

/**
 * Отладочная информация о выполнении запроса.
 *
 * Содержит подготовленный запрос, ответ провайдера, время выполнения
 * и вложенные данные (для batch/pool операций).
 *
 * Используется в:
 * - ExecutionResultBuilder::buildSuccess() - создаётся при успешном выполнении
 * - ExecutionResultBuilder::buildCriticalError() - создаётся при критической ошибке
 * - DebugInfo::$nested - хранит отладочную информацию вложенных запросов
 */
readonly class DebugInfo
{
    /**
     * @param PreparedRequest|null $preparedRequest Подготовленный HTTP-запрос (метод, URL, заголовки, тело)
     * @param ProviderResponse|null $response Ответ от провайдера (статус, заголовки, тело)
     * @param float|null $duration Время выполнения запроса в секундах
     * @param array<DebugInfo> $nested Отладочная информация вложенных запросов (batch/pool)
     */
    public function __construct(
        public ?PreparedRequest $preparedRequest = null,
        public ?ProviderResponse $response = null,
        public ?float $duration = null,
        public array $nested = [],
    ) {}
}
