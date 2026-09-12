<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\Errors;

enum ErrorCode: string
{
    case ExecutionError = 'execution_error';
    case FileTransferError = 'file_transfer_error';
    case HookError = 'hook_error';
    case ResponseDecodingError = 'response_decoding_error';
    case TransportError = 'transport_error';
    case InvalidRequest = 'invalid_request';
    case BadRequest = 'bad_request';
    case ClientError = 'client_error';

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

    public static function fromHttpStatus(int $status): self
    {
        return match ($status) {
            400 => self::BadRequest,
            401 => self::Unauthorized,
            403 => self::Forbidden,
            404 => self::NotFound,
            408 => self::Timeout,
            422 => self::ValidationFailed,
            429 => self::RateLimited,
            502 => self::BadGateway,
            503 => self::ServiceUnavailable,
            504 => self::GatewayTimeout,
            default => $status >= 400 && $status < 500 ? self::ClientError : self::ServerError,
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::ExecutionError => 'Ошибка исполнения',
            self::FileTransferError => 'Ошибка файловой передачи',
            self::HookError => 'Ошибка hook',
            self::ResponseDecodingError => 'Ошибка разбора ответа',
            self::TransportError => 'Ошибка транспорта',
            self::InvalidRequest => 'Некорректный HTTP-запрос',
            self::BadRequest => 'Некорректный запрос',
            self::ClientError => 'Ошибка клиента',

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
