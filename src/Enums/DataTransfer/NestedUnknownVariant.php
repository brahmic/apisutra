<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Enums\DataTransfer;

/**
 * Политика обработки неизвестного discriminator в Nested.
 */
enum NestedUnknownVariant: string
{
    /**
     * Оставить элемент в raw-виде.
     */
    case KeepRaw = 'keep_raw';

    /**
     * Пропустить элемент.
     */
    case Skip = 'skip';

    /**
     * Бросить исключение.
     */
    case Error = 'error';
}
