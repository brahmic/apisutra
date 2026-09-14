<?php

declare(strict_types=1);

namespace Brahmic\ApiSutra\Tests\Stubs\HydrationRules;

use Brahmic\ApiSutra\DataTransfer\AbstractResponseDto;
use Brahmic\ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ComputedRecordDto extends AbstractResponseDto
{
    public function __construct(public int $id)
    {
    }

    public static function computed(array $data, ?PipelineContext $context = null): array
    {
        return ['id' => $data['source']];
    }
}
