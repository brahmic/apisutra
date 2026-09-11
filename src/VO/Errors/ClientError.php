<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\VO\Errors;

use Brahmic\ApiSutra\Enums\Errors\ErrorCode;

/**
 * Ошибка для клиентского ответа.
 */
final readonly class ClientError
{
    /**
     * @param array<string, mixed> $context
     * @param array<ClientError> $nested
     */
    public function __construct(
        public ?string $providerCode,
        public ErrorCode $sdkCode,
        public ?string $clientCode,
        public ?string $appCode,
        public string $message,
        public array $context = [],
        public array $nested = [],
        public ?string $requestClass = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_code' => $this->providerCode,
            'sdk_code' => $this->sdkCode->value,
            'client_code' => $this->clientCode,
            'app_code' => $this->appCode,
            'message' => $this->message,
            'context' => $this->context,
            'nested' => array_map(
                static fn (ClientError $error): array => $error->toArray(),
                $this->nested,
            ),
            'request_class' => $this->requestClass,
        ];
    }
}
