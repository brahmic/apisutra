<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\VO\Http;

use Brahmic\ApiSutra\Support\ArrayPath;

/**
 * Value Object для HTTP-ответа от провайдера.
 *
 * Содержит полную информацию об ответе API: статус код, заголовки, тело ответа,
 * оригинальный запрос и время выполнения. Предоставляет методы для работы с ответом:
 * декодирование JSON, проверка статуса, получение заголовков.
 *
 * Используется в:
 * - Transport (HttpTransport, MockTransport) - создаётся после выполнения HTTP-запроса
 * - Pipeline handlers (AuthHandler, CacheManager, ErrorPolicy) - обрабатывают ответ
 * - ResponseHydrator - гидратирует ответ в DTO
 * - RetryHandler/RetryDecisionMaker - принимают решение о повторе запроса
 * - ExtensionRegistry - определяет обработчик для Content-Type
 */
readonly class ProviderResponse
{
    /**
     * @param int $status HTTP статус код ответа (200, 404, 500 и т.д.)
     * @param array<string, array<int, string>> $headers HTTP заголовки (key => [values])
     * @param string $body Тело ответа (raw content)
     * @param PreparedRequest $request Оригинальный подготовленный запрос
     * @param float $duration Время выполнения запроса в секундах
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public PreparedRequest $request,
        public float $duration,
    ) {}

    /**
     * Декодирует JSON-тело ответа и возвращает данные.
     * Поддерживает извлечение вложенных полей через dot-нотацию.
     *
     * @param string|null $key Путь к полю через точку (например, 'data.user.name')
     * @return mixed Декодированные данные или null
     */
    public function json(?string $key = null): mixed
    {
        $data = json_decode($this->body, true);
        if ($key === null) {
            return $data;
        }

        return ArrayPath::getByPath($data, $key);
    }

    /**
     * Проверяет, является ли ответ успешным (2xx).
     *
     * @return bool true если статус 200-299
     */
    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Проверяет, является ли ответ клиентской ошибкой (4xx).
     *
     * @return bool true если статус 400-499
     */
    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * Проверяет, является ли ответ серверной ошибкой (5xx).
     *
     * @return bool true если статус 500+
     */
    public function isServerError(): bool
    {
        return $this->status >= 500;
    }

    /**
     * Возвращает значение конкретного заголовка (case-insensitive).
     *
     * @param string $name Имя заголовка
     * @return string|null Значение заголовка или null
     */
    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

}
