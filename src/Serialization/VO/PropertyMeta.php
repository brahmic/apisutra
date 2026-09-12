<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Serialization\VO;

use Brahmic\ApiSutra\Attributes\DataTransfer\Cast as CastAttribute;
use Brahmic\ApiSutra\Attributes\Request\Body;
use Brahmic\ApiSutra\Attributes\Request\BodyRoot;
use Brahmic\ApiSutra\Attributes\Request\File;
use Brahmic\ApiSutra\Attributes\Request\Header;
use Brahmic\ApiSutra\Attributes\Request\Ignore;
use Brahmic\ApiSutra\Attributes\Request\Path;
use Brahmic\ApiSutra\Attributes\Request\Query;
use ReflectionProperty;

final readonly class PropertyMeta
{
    public function __construct(
        public string $name,
        public ReflectionProperty $property,
        public bool $isPublic,
        public bool $isStatic,
        public ?Ignore $ignore,
        public ?Path $path,
        public ?Query $query,
        public ?Body $body,
        public ?BodyRoot $bodyRoot,
        public ?Header $header,
        public ?File $file,
        public ?CastAttribute $cast,
    ) {
    }

    /** Свойство пропускается при сериализации */
    public function shouldSkip(): bool
    {
        return $this->isStatic || $this->ignore !== null || !$this->isPublic;
    }
}
