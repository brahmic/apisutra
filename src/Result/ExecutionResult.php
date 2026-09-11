<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Result;

use Brahmic\ApiSutra\Collections\ErrorCollection;
use Brahmic\ApiSutra\Collections\ResultCollection;
use Brahmic\ApiSutra\Contracts\Interfaces\Core\ResultInterface;
use Brahmic\ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use Brahmic\ApiSutra\Enums\Result\ResultStatus;
use Brahmic\ApiSutra\Exceptions\Core\SdkException;
use Brahmic\ApiSutra\VO\Audit\DebugInfo;
use Brahmic\ApiSutra\VO\Audit\PipelineEvent;
use Brahmic\ApiSutra\VO\Errors\ValidationError;
use Brahmic\ApiSutra\VO\Http\ProviderResponse;
use Override;
use Throwable;

/**
 * Результат выполнения запроса с данными, ошибками и аудитом.
 *
 * Нюансы:
 * - data может быть null даже при SUCCESS (например, пустой ответ).
 * - errors описывает ошибки выполнения/транспорта, validationErrors — отдельный список ошибок валидации.
 * - exception может отсутствовать, если ошибка зафиксирована только через errors.
 * - nested заполняется для composite/depends-on и доступен как массив и как коллекция.
 */
readonly class ExecutionResult implements ResultInterface
{
    private const array SENSITIVE_HEADERS = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'api-key',
        'x-auth-token',
        'x-access-token',
    ];

    private const array DEFAULT_SENSITIVE_KEYS = [
        'password',
        'token',
        'secret',
        'api_key',
        'apikey',
        'client_secret',
        'access_token',
        'refresh_token',
    ];

    private ResultCollection $nestedResults;

    /**
     * @param array<ValidationError> $validationErrors Ошибки валидации входных данных.
     * @param array<PipelineEvent> $audit Хронология этапов пайплайна (может быть пустой).
     * @param array<ExecutionResult> $nested Результаты вложенных запросов (composite/depends-on).
     */
    public function __construct(
        public mixed $data,
        public ResultStatus $status,
        public ErrorCollection $errors,
        public array $validationErrors = [],
        public ?DebugInfo $debug = null,
        public ?string $traceId = null,
        public array $audit = [],
        public ?ResultMeta $meta = null,
        public array $nested = [],
        public ?string $requestClass = null,
        public ?Throwable $exception = null,
        public ?ProviderResponse $response = null,
    ) {
        $this->nestedResults = ResultCollection::make($this->nested);
    }

    /**
     * Успешное завершение, без анализа состава данных и ошибок.
     */
    #[Override]
    public function isSuccess(): bool
    {
        return $this->status === ResultStatus::SUCCESS;
    }

    /**
     * Частичный успех (обычно при composite с ошибками отдельных шагов).
     */
    public function isPartial(): bool
    {
        return $this->status === ResultStatus::PARTIAL;
    }

    /**
     * Провал выполнения, даже если data может быть заполнен частично.
     */
    #[Override]
    public function isFailed(): bool
    {
        return $this->status === ResultStatus::FAILED;
    }

    /**
     * Данные присутствуют, но это не гарантирует статус SUCCESS.
     */
    #[Override]
    public function hasData(): bool
    {
        return $this->data !== null;
    }

    /**
     * Ошибки выполнения/транспорта (не включает validationErrors как список полей).
     */
    #[Override]
    public function hasErrors(): bool
    {
        return $this->errors->first() !== null;
    }

    /**
     * Отдельный список ошибок валидации входных данных.
     */
    public function hasValidationErrors(): bool
    {
        return $this->validationErrors !== [];
    }

    /**
     * Возвращает первую ошибку валидации для поля, если есть.
     */
    public function validationErrorFor(string $field): ?ValidationError
    {
        foreach ($this->validationErrors as $error) {
            if ($error->field === $field) {
                return $error;
            }
        }

        return null;
    }

    /**
     * Бросает исключение только при FAILED, иначе возвращает self.
     */
    public function throw(): self
    {
        if ($this->isFailed()) {
            if ($this->exception instanceof Throwable) {
                throw $this->exception;
            }

            $message = $this->errors->first()?->message ?? 'Ошибка выполнения запроса';
            throw new SdkException($message);
        }

        return $this;
    }

    /**
     * Краткое сообщение об ошибках: первая ошибка + количество при множественных.
     */
    public function message(): ?string
    {
        $first = $this->errors->first();
        $count = $first === null ? 0 : count($this->errors->all());

        return match (true) {
            $first === null => null,
            $count <= 1 => $first->message,
            default => 'Несколько ошибок: ' . $count . '. ' . $first->message,
        };
    }

    /**
     * Возвращает вложенные результаты как коллекцию для удобной агрегации.
     */
    protected function nestedResults(): ResultCollection
    {
        return $this->nestedResults;
    }

    /**
     * Вернуть debug-снимок подготовленного HTTP-запроса.
     *
     * @return array{
     *   method: string,
     *   url: string,
     *   headers: array<string, string>,
     *   bodyRaw: ?string,
     *   body: mixed,
     *   query: array<string, mixed>|null,
     *   form: array<string, mixed>|null,
     *   hasStream: bool,
     *   oneOf: array<string, mixed>|null,
     *   credentialsEnrichment: array<string, mixed>|null
     * }|null
     */
    public function requestDebug(bool $redactSensitive = true): ?array
    {
        $prepared = $this->debug?->preparedRequest;
        if ($prepared === null) {
            return null;
        }

        $headers = $redactSensitive
            ? $this->redactHeaders($prepared->headers)
            : $prepared->headers;
        $credentials = is_array($prepared->meta['credentialsEnrichment'] ?? null)
            ? $prepared->meta['credentialsEnrichment']
            : null;
        $secretKeys = $this->resolveSecretKeys($credentials);

        $body = $prepared->meta['body'] ?? null;
        $query = is_array($prepared->meta['query'] ?? null) ? $prepared->meta['query'] : null;
        $form = $this->extractForm($prepared->meta['body'] ?? null, $prepared->headers);
        $bodyRaw = $prepared->body;

        if ($redactSensitive) {
            $body = $this->redactByKeys($body, $secretKeys);
            $query = is_array($query) ? $this->redactQuery($query, $secretKeys) : null;
            $form = is_array($form) ? $this->redactByKeys($form, $secretKeys) : null;
            $bodyRaw = $this->redactBodyRaw($bodyRaw, $secretKeys);
        }

        return [
            'method' => $prepared->method->value,
            'url' => $prepared->url,
            'headers' => $headers,
            'bodyRaw' => $bodyRaw,
            'body' => $body,
            'query' => $query,
            'form' => $form,
            'hasStream' => $prepared->stream !== null,
            'oneOf' => is_array($prepared->meta['oneOf'] ?? null) ? $prepared->meta['oneOf'] : null,
            'credentialsEnrichment' => $credentials,
        ];
    }

    public function requestDebugJson(
        bool $redactSensitive = true,
        int $flags = JSON_UNESCAPED_UNICODE,
    ): ?string {
        $snapshot = $this->requestDebug($redactSensitive);
        if ($snapshot === null) {
            return null;
        }

        $encoded = json_encode($snapshot, $flags);

        return is_string($encoded) ? $encoded : null;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function redactHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            $result[$name] = in_array(strtolower($name), self::SENSITIVE_HEADERS, true)
                ? '***'
                : $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $credentials
     * @return array<int, string>
     */
    private function resolveSecretKeys(?array $credentials): array
    {
        $keys = self::DEFAULT_SENSITIVE_KEYS;
        if (is_array($credentials['secretKeys'] ?? null)) {
            $keys = [...$keys, ...$credentials['secretKeys']];
        }

        return $this->normalizeSecretKeys($keys);
    }

    /**
     * @param array<int, string> $keys
     * @return array<int, string>
     */
    private function normalizeSecretKeys(array $keys): array
    {
        $normalized = [];
        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }

            $trimmed = strtolower(trim($key));
            if ($trimmed === '') {
                continue;
            }

            $normalized[] = $trimmed;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function extractForm(mixed $body, array $headers): ?array
    {
        if (!is_array($body)) {
            return null;
        }

        $contentType = strtolower($headers['Content-Type'] ?? '');
        if (!str_contains($contentType, 'multipart/form-data')) {
            return null;
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<int, string> $secretKeys
     * @return array<string, mixed>
     */
    private function redactQuery(array $query, array $secretKeys): array
    {
        $result = [];
        foreach ($query as $key => $data) {
            if (!is_array($data)) {
                $result[$key] = $data;
                continue;
            }

            $item = $data;
            if ($this->isSensitiveKey((string) $key, $secretKeys)) {
                $item['value'] = '***';
            } else {
                $item['value'] = $this->redactByKeys($item['value'] ?? null, $secretKeys);
            }
            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * @param array<int, string> $secretKeys
     */
    private function redactByKeys(mixed $value, array $secretKeys): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSensitiveKey($key, $secretKeys)) {
                $result[$key] = '***';
                continue;
            }

            $result[$key] = $this->redactByKeys($item, $secretKeys);
        }

        return $result;
    }

    /**
     * @param array<int, string> $secretKeys
     */
    private function redactBodyRaw(?string $bodyRaw, array $secretKeys): ?string
    {
        if ($bodyRaw === null || trim($bodyRaw) === '') {
            return $bodyRaw;
        }

        $decoded = json_decode($bodyRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $bodyRaw;
        }

        $redacted = $this->redactByKeys($decoded, $secretKeys);
        $encoded = json_encode($redacted, JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : $bodyRaw;
    }

    /**
     * @param array<int, string> $secretKeys
     */
    private function isSensitiveKey(string $key, array $secretKeys): bool
    {
        return in_array(strtolower(trim($key)), $secretKeys, true);
    }
}
