<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Catalog\Standalone;

use Brahmic\ApiSutra\Attributes\Http\Get;
use Brahmic\ApiSutra\Core\AbstractRequest;

/**
 * Без #[Returns], без #[ContinuationResult], без #[Download], без \Resources\ в namespace.
 * Не должен попасть ни в один список response DTO.
 */
#[Get('/catalog/standalone')]
final class CatalogStandaloneRequest extends AbstractRequest
{
}
