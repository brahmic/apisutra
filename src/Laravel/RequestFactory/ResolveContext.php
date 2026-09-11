<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Laravel\RequestFactory;

use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;
use ReflectionProperty;

readonly class ResolveContext
{
    /**
     * @param array<string, mixed> $payload Нормализованные данные запроса.
     * @param array<int, string> $placeholders Плейсхолдеры пути.
     */
    public function __construct(
        public ReflectionProperty $property,
        public array $payload,
        public array $placeholders,
        public bool $isQueryMethod,
        public string $propertyName,
        public ?Path $pathAttribute,
        public ?Query $queryAttribute,
        public ?Body $bodyAttribute,
        public ?Header $headerAttribute,
        public ?File $fileAttribute,
    ) {
    }
}
