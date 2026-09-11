<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Contracts\Interfaces\Attributes;

use Brahmic\ApiSutra\Attributes\AttributeMetadataCache;

interface AttributeMetadataCacheProviderInterface
{
    /**
     * Получить кеш метаданных атрибутов
     */
    public function getAttributeMetadataCache(): AttributeMetadataCache;
}
