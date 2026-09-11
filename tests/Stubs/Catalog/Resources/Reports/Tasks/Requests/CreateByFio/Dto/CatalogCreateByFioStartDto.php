<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\Catalog\Resources\Reports\Tasks\Requests\CreateByFio\Dto;

final readonly class CatalogCreateByFioStartDto
{
    public function __construct(
        public string $taskId,
    ) {}
}
