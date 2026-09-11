<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Attributes\Response;

use Attribute;
use Brahmic\ApiSutra\Enums\Continuation\ContinuationMode;

/**
 * Декларативный контракт финального результата для optional async request-класса.
 *
 * Поля:
 * - finalType: обязательный DTO-класс финала для await().
 * - unwrap: optional путь к финальному payload внутри envelope.
 * - pollRequest: optional poll-request; если не задан, используется ClientConfig.defaultPollRequest.
 * - defaultMode: optional mode по умолчанию для класса запроса.
 *
 * Приоритет mode-resolve:
 * runtime override -> defaultMode атрибута -> ClientConfig.defaultContinuationMode.
 *
 * @see docs/guides/provider-async-await.md
 * @see docs/guides/attributes/response.md
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ContinuationResult
{
    public function __construct(
        public string $finalType,
        public ?string $unwrap = null,
        public ?string $pollRequest = null,
        public ?ContinuationMode $defaultMode = null,
    ) {}
}
