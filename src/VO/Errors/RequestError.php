<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\VO\Errors;

use Brahmic\ApiSutra\Enums\Errors\ErrorCode;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;

/**
 * Value Object для ошибки выполнения запроса.
 *
 * Представляет ошибку, возникшую при выполнении запроса к API провайдера.
 * Содержит код ошибки, сообщение, ответ провайдера (если есть), вложенные ошибки
 * (для batch/pool операций), контекстную информацию и класс запроса.
 *
 * Используется в:
 * - ErrorPolicy - создаёт ошибки из исключений и неуспешных ответов
 * - ExecutionResultBuilder - добавляет ошибки в результат
 * - BatchExecutor/PoolExecutor - собирает ошибки вложенных запросов
 * - ExecutionResult::$errors - хранит коллекцию ошибок
 * - Paginator - создаёт ошибки при неудачной пагинации
 */
readonly class RequestError
{
    /**
     * @param ErrorCode $code Код ошибки (NetworkError, ServerError, ValidationError и т.д.)
     * @param string $message Сообщение об ошибке для пользователя
     * @param ProviderResponse|null $response Ответ провайдера (если был получен)
     * @param array<RequestError> $nested Вложенные ошибки (для batch/pool операций)
     * @param array<string, mixed> $context Дополнительный контекст ошибки (параметры, метаданные)
     * @param string|null $requestClass Класс запроса, в котором произошла ошибка (FQCN)
     */
    public function __construct(
        public ErrorCode $code,
        public string $message,
        public ?ProviderResponse $response = null,
        public array $nested = [],
        public array $context = [],
        public ?string $requestClass = null,
    ) {}
}

