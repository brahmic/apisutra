<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Errors;

enum ErrorCode: string
{
    case ConnectionFailed = 'connection_failed';
    case Timeout = 'timeout';
    case DnsError = 'dns_error';

    case Unauthorized = 'unauthorized';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case ValidationFailed = 'validation_failed';
    case RateLimited = 'rate_limited';

    case ServerError = 'server_error';
    case BadGateway = 'bad_gateway';
    case ServiceUnavailable = 'service_unavailable';
    case GatewayTimeout = 'gateway_timeout';

    case ConfigurationError = 'configuration_error';
    case HydrationError = 'hydration_error';
    case SerializationError = 'serialization_error';
    case ExtensionError = 'extension_error';
    case RequestContractViolation = 'request_contract_violation';

    public function title(): string
    {
        return match ($this) {
            self::ConnectionFailed => 'Ошибка подключения',
            self::Timeout => 'Таймаут',
            self::DnsError => 'Ошибка DNS',
            self::Unauthorized => 'Не авторизован',
            self::Forbidden => 'Доступ запрещен',
            self::NotFound => 'Не найдено',
            self::ValidationFailed => 'Ошибка валидации',
            self::RateLimited => 'Превышен лимит запросов',
            self::ServerError => 'Ошибка сервера',
            self::BadGateway => 'Плохой шлюз',
            self::ServiceUnavailable => 'Сервис недоступен',
            self::GatewayTimeout => 'Таймаут шлюза',
            self::ConfigurationError => 'Ошибка конфигурации',
            self::HydrationError => 'Ошибка гидратации',
            self::SerializationError => 'Ошибка сериализации',
            self::ExtensionError => 'Ошибка расширения',
            self::RequestContractViolation => 'Нарушение контракта запроса',
        };
    }
}
